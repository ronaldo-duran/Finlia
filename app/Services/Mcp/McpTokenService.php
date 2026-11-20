<?php

declare(strict_types=1);

namespace App\Services\Mcp;

use App\Models\Household;
use App\Models\McpToken;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Emisión, verificación y revocación de tokens MCP (ADR-0049).
 *
 * Seam (ADR-0010): no toca request/session/Auth. Recibe usuario, hogar y
 * datos explícitos. La generación es el único punto del sistema donde el
 * string en claro existe: todo lo demás trabaja con el hash.
 */
class McpTokenService
{
    /**
     * Longitud en bytes del material aleatorio del token. 32 bytes →
     * 64 chars hex, suficientemente ancho para descartar fuerza bruta.
     */
    private const SECRET_BYTES = 32;

    /**
     * @param  list<string>  $abilities
     * @return array{token: McpToken, plain: string}
     */
    public function issue(
        User $user,
        Household $household,
        string $name,
        array $abilities,
        ?Carbon $expiresAt = null,
    ): array {
        $plain = McpToken::PLAIN_PREFIX.bin2hex(random_bytes(self::SECRET_BYTES));

        $token = McpToken::query()->create([
            'user_id' => $user->id,
            'household_id' => $household->id,
            'name' => trim($name),
            'token_hash' => $this->hash($plain),
            'abilities' => array_values(array_unique($abilities)),
            'expires_at' => $expiresAt,
        ]);

        return ['token' => $token, 'plain' => $plain];
    }

    /**
     * Resuelve un token activo a partir del string en claro del header.
     * Devuelve null cuando no coincide o está revocado/vencido, nunca lanza.
     */
    public function findActive(string $plain): ?McpToken
    {
        if (! Str::startsWith($plain, McpToken::PLAIN_PREFIX)) {
            return null;
        }

        return McpToken::query()
            ->active()
            ->where('token_hash', $this->hash($plain))
            ->first();
    }

    public function touch(McpToken $token): void
    {
        $token->forceFill(['last_used_at' => now()])->save();
    }

    public function revoke(McpToken $token): void
    {
        if ($token->revoked_at !== null) {
            return;
        }

        $token->forceFill(['revoked_at' => now()])->save();
    }

    public function hash(string $plain): string
    {
        return hash('sha256', $plain);
    }
}
