<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Mcp\McpAuthorization;
use Database\Factories\McpTokenFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Token personal de acceso MCP (ADR-0049).
 *
 * Cada token queda atado al usuario Y a un hogar fijado al emitirlo, de modo
 * que el aislamiento multi-hogar no dependa de ningún parámetro del cliente.
 * Nunca se guarda el token en claro: `token_hash` es un SHA-256 del string
 * secreto, que se muestra al usuario una sola vez tras emitirlo.
 */
#[Fillable(['user_id', 'household_id', 'name', 'token_hash', 'abilities', 'last_used_at', 'expires_at', 'revoked_at'])]
class McpToken extends Model implements McpAuthorization
{
    /** @use HasFactory<McpTokenFactory> */
    use HasFactory;

    /**
     * Prefijo visible del token en claro. Marca el formato al que luego hay
     * que exigir cuando un header llega sin él.
     */
    public const PLAIN_PREFIX = 'finlia_mcp_';

    /**
     * Habilidad para operaciones de solo lectura (consultas y resúmenes).
     * Las futuras tools de escritura exigirán una habilidad distinta.
     */
    public const ABILITY_READ = 'read';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'abilities' => 'array',
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    /**
     * Token activo: ni revocado ni vencido. Se usa como scope de lookup.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at')
            ->where(function (Builder $q): void {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function hasAbility(string $ability): bool
    {
        return in_array($ability, (array) $this->abilities, true);
    }
}
