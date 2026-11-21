<?php

declare(strict_types=1);

namespace App\Services\Mcp;

use App\Models\Household;
use App\Models\OAuthAccessToken;
use App\Models\OAuthAuthorizationCode;
use App\Models\OAuthClient;
use App\Models\OAuthRefreshToken;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Flujo OAuth 2.1 + PKCE para conectores MCP externos (claude.ai, ChatGPT).
 *
 * Seam (ADR-0010): este servicio no sabe de HTTP. Recibe DTOs/IDs explícitos
 * y devuelve resultados o lanza OAuthException con el error_code normativo
 * ("invalid_grant", "invalid_request", etc.). El controlador traduce esas
 * excepciones al formato de respuesta del endpoint correspondiente.
 */
class OAuthService
{
    /**
     * Vida del authorization_code: ventana corta porque solo vive entre el
     * consentimiento y el POST /oauth/token inmediato del cliente.
     */
    public const AUTHORIZATION_CODE_TTL_SECONDS = 60;

    /**
     * Vida del access token. 1 hora es el estándar; el cliente lo renueva
     * con el refresh token cuando se acerca a expirar.
     */
    public const ACCESS_TOKEN_TTL_SECONDS = 3600;

    /**
     * Vida del refresh token. 30 días: suficiente para un uso cotidiano,
     * y corto si el cliente deja de ser confiable.
     */
    public const REFRESH_TOKEN_TTL_SECONDS = 60 * 60 * 24 * 30;

    private const SECRET_BYTES = 32;

    /**
     * Registra un cliente dinámicamente (RFC 7591).
     *
     * @param  list<string>  $redirectUris
     */
    public function registerClient(
        string $clientName,
        array $redirectUris,
        ?string $clientUri = null,
        ?string $logoUri = null,
        ?string $softwareId = null,
        ?string $softwareVersion = null,
    ): OAuthClient {
        return OAuthClient::query()->create([
            'client_id' => 'finlia_client_'.bin2hex(random_bytes(16)),
            'client_name' => trim($clientName),
            'redirect_uris' => array_values(array_unique($redirectUris)),
            'grant_types' => ['authorization_code', 'refresh_token'],
            'response_types' => ['code'],
            'token_endpoint_auth_method' => 'none',
            'scope' => 'mcp:read',
            'client_uri' => $clientUri,
            'logo_uri' => $logoUri,
            'software_id' => $softwareId,
            'software_version' => $softwareVersion,
        ]);
    }

    public function findClient(string $clientId): ?OAuthClient
    {
        return OAuthClient::query()->where('client_id', $clientId)->first();
    }

    /**
     * Emite un authorization_code tras el consentimiento del usuario.
     */
    public function issueAuthorizationCode(
        OAuthClient $client,
        User $user,
        Household $household,
        string $redirectUri,
        string $codeChallenge,
        string $codeChallengeMethod,
        string $scope,
    ): string {
        $plain = Str::random(48);

        OAuthAuthorizationCode::query()->create([
            'code_hash' => $this->hash($plain),
            'oauth_client_id' => $client->id,
            'user_id' => $user->id,
            'household_id' => $household->id,
            'redirect_uri' => $redirectUri,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => $codeChallengeMethod,
            'scope' => $scope,
            'expires_at' => now()->addSeconds(self::AUTHORIZATION_CODE_TTL_SECONDS),
        ]);

        return $plain;
    }

    /**
     * Canjea un authorization_code + code_verifier por access y refresh token.
     *
     * @return array{access_token: string, refresh_token: string, expires_in: int, scope: string}
     *
     * @throws OAuthException
     */
    public function exchangeAuthorizationCode(
        string $clientId,
        string $code,
        string $redirectUri,
        string $codeVerifier,
    ): array {
        $client = $this->findClient($clientId);
        if ($client === null) {
            throw new OAuthException('invalid_client', 'client_id no reconocido', 401);
        }

        return DB::transaction(function () use ($client, $code, $redirectUri, $codeVerifier): array {
            $authCode = OAuthAuthorizationCode::query()
                ->where('oauth_client_id', $client->id)
                ->where('code_hash', $this->hash($code))
                ->lockForUpdate()
                ->first();

            if ($authCode === null || ! $authCode->isUsable()) {
                throw new OAuthException('invalid_grant', 'Código inválido, vencido o ya usado');
            }

            if ($authCode->redirect_uri !== $redirectUri) {
                throw new OAuthException('invalid_grant', 'redirect_uri no coincide con el usado en /oauth/authorize');
            }

            if (! $this->verifyPkce($authCode->code_challenge, $authCode->code_challenge_method, $codeVerifier)) {
                throw new OAuthException('invalid_grant', 'code_verifier inválido');
            }

            $authCode->forceFill(['used_at' => now()])->save();

            return $this->issueTokenPair(
                $client,
                $authCode->user_id,
                $authCode->household_id,
                $authCode->scope,
            );
        });
    }

    /**
     * Rota un refresh token: emite un par nuevo y revoca el anterior.
     *
     * @return array{access_token: string, refresh_token: string, expires_in: int, scope: string}
     *
     * @throws OAuthException
     */
    public function refresh(string $clientId, string $refreshToken): array
    {
        $client = $this->findClient($clientId);
        if ($client === null) {
            throw new OAuthException('invalid_client', 'client_id no reconocido', 401);
        }

        return DB::transaction(function () use ($client, $refreshToken): array {
            $existing = OAuthRefreshToken::query()
                ->where('oauth_client_id', $client->id)
                ->where('token_hash', $this->hash($refreshToken))
                ->lockForUpdate()
                ->first();

            if ($existing === null || ! $existing->isUsable()) {
                throw new OAuthException('invalid_grant', 'Refresh token inválido, vencido o revocado');
            }

            $existing->forceFill(['revoked_at' => now()])->save();

            if ($existing->oauth_access_token_id !== null) {
                OAuthAccessToken::query()
                    ->whereKey($existing->oauth_access_token_id)
                    ->whereNull('revoked_at')
                    ->update(['revoked_at' => now()]);
            }

            return $this->issueTokenPair(
                $client,
                $existing->user_id,
                $existing->household_id,
                $existing->scope,
            );
        });
    }

    public function findActiveAccessToken(string $plain): ?OAuthAccessToken
    {
        if (! Str::startsWith($plain, OAuthAccessToken::PLAIN_PREFIX)) {
            return null;
        }

        return OAuthAccessToken::query()
            ->active()
            ->where('token_hash', $this->hash($plain))
            ->first();
    }

    public function touchAccessToken(OAuthAccessToken $token): void
    {
        $token->forceFill(['last_used_at' => now()])->save();
    }

    public function hash(string $plain): string
    {
        return hash('sha256', $plain);
    }

    /**
     * Verifica un PKCE challenge contra su verifier.
     */
    public function verifyPkce(string $challenge, string $method, string $verifier): bool
    {
        if ($method === 'S256') {
            $computed = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

            return hash_equals($challenge, $computed);
        }

        if ($method === 'plain') {
            return hash_equals($challenge, $verifier);
        }

        return false;
    }

    /**
     * @return array{access_token: string, refresh_token: string, expires_in: int, scope: string}
     */
    private function issueTokenPair(OAuthClient $client, int $userId, int $householdId, string $scope): array
    {
        $accessPlain = OAuthAccessToken::PLAIN_PREFIX.bin2hex(random_bytes(self::SECRET_BYTES));
        $refreshPlain = OAuthRefreshToken::PLAIN_PREFIX.bin2hex(random_bytes(self::SECRET_BYTES));

        $access = OAuthAccessToken::query()->create([
            'token_hash' => $this->hash($accessPlain),
            'oauth_client_id' => $client->id,
            'user_id' => $userId,
            'household_id' => $householdId,
            'scope' => $scope,
            'expires_at' => now()->addSeconds(self::ACCESS_TOKEN_TTL_SECONDS),
        ]);

        OAuthRefreshToken::query()->create([
            'token_hash' => $this->hash($refreshPlain),
            'oauth_client_id' => $client->id,
            'user_id' => $userId,
            'household_id' => $householdId,
            'oauth_access_token_id' => $access->id,
            'scope' => $scope,
            'expires_at' => now()->addSeconds(self::REFRESH_TOKEN_TTL_SECONDS),
        ]);

        return [
            'access_token' => $accessPlain,
            'refresh_token' => $refreshPlain,
            'expires_in' => self::ACCESS_TOKEN_TTL_SECONDS,
            'scope' => $scope,
        ];
    }
}
