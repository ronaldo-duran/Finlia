<?php

declare(strict_types=1);

namespace App\Http\Controllers\OAuth;

use App\Http\Controllers\Controller;
use App\Services\Mcp\OAuthException;
use App\Services\Mcp\OAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Token endpoint OAuth 2.1.
 *
 * Soporta authorization_code (con PKCE) y refresh_token. No autenticación
 * de cliente porque son clientes públicos; la seguridad del intercambio
 * viene del code_verifier (PKCE) y de la rotación del refresh token.
 */
class TokenController extends Controller
{
    public function __invoke(Request $request, OAuthService $oauth): JsonResponse
    {
        $grantType = (string) $request->input('grant_type', '');

        try {
            $tokens = match ($grantType) {
                'authorization_code' => $this->handleAuthorizationCode($request, $oauth),
                'refresh_token' => $this->handleRefresh($request, $oauth),
                default => throw new OAuthException(
                    'unsupported_grant_type',
                    'grant_type debe ser authorization_code o refresh_token',
                ),
            };
        } catch (OAuthException $e) {
            return $this->error($e);
        }

        return response()->json([
            'access_token' => $tokens['access_token'],
            'token_type' => 'Bearer',
            'expires_in' => $tokens['expires_in'],
            'refresh_token' => $tokens['refresh_token'],
            'scope' => $tokens['scope'],
        ])->header('Cache-Control', 'no-store')
            ->header('Pragma', 'no-cache');
    }

    /**
     * @return array{access_token: string, refresh_token: string, expires_in: int, scope: string}
     */
    private function handleAuthorizationCode(Request $request, OAuthService $oauth): array
    {
        $validator = Validator::make($request->all(), [
            'code' => ['required', 'string'],
            'client_id' => ['required', 'string'],
            'redirect_uri' => ['required', 'url:https,http'],
            'code_verifier' => ['required', 'string', 'min:43', 'max:128'],
        ]);

        if ($validator->fails()) {
            throw new OAuthException('invalid_request', $validator->errors()->first());
        }

        $data = $validator->validated();

        return $oauth->exchangeAuthorizationCode(
            clientId: $data['client_id'],
            code: $data['code'],
            redirectUri: $data['redirect_uri'],
            codeVerifier: $data['code_verifier'],
        );
    }

    /**
     * @return array{access_token: string, refresh_token: string, expires_in: int, scope: string}
     */
    private function handleRefresh(Request $request, OAuthService $oauth): array
    {
        $validator = Validator::make($request->all(), [
            'refresh_token' => ['required', 'string'],
            'client_id' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            throw new OAuthException('invalid_request', $validator->errors()->first());
        }

        $data = $validator->validated();

        return $oauth->refresh($data['client_id'], $data['refresh_token']);
    }

    private function error(OAuthException $e): JsonResponse
    {
        return response()->json([
            'error' => $e->errorCode,
            'error_description' => $e->getMessage(),
        ], $e->httpStatus)->header('Cache-Control', 'no-store');
    }
}
