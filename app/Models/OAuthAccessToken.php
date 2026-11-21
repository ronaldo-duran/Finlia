<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Mcp\McpAuthorization;
use Database\Factories\OAuthAccessTokenFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Access token OAuth 2.1 emitido al cliente MCP tras consentimiento.
 *
 * Lo presenta el cliente en el header Authorization: Bearer finlia_oat_...
 * contra el endpoint /mcp. Su hogar está fijado al momento de la autorización
 * (como con los PAT) y los scopes acotan qué tools puede invocar.
 */
#[Fillable([
    'token_hash',
    'oauth_client_id',
    'user_id',
    'household_id',
    'scope',
    'last_used_at',
    'expires_at',
    'revoked_at',
])]
class OAuthAccessToken extends Model implements McpAuthorization
{
    /** @use HasFactory<OAuthAccessTokenFactory> */
    use HasFactory;

    protected $table = 'oauth_access_tokens';

    public const PLAIN_PREFIX = 'finlia_oat_';

    /**
     * Mapeo scope OAuth → ability MCP. "mcp:read" habilita tools de lectura
     * para el mismo seam que un PAT con ability "read".
     */
    private const SCOPE_TO_ABILITY = [
        'mcp:read' => McpToken::ABILITY_READ,
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(OAuthClient::class, 'oauth_client_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at')->where('expires_at', '>', now());
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function hasScope(string $scope): bool
    {
        return in_array($scope, explode(' ', trim((string) $this->scope)), true);
    }

    public function hasAbility(string $ability): bool
    {
        foreach (explode(' ', trim((string) $this->scope)) as $scope) {
            if (($mapped = self::SCOPE_TO_ABILITY[$scope] ?? null) !== null && $mapped === $ability) {
                return true;
            }
        }

        return false;
    }
}
