<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Refresh token OAuth 2.1 ligado a un access token.
 *
 * Permite al cliente renovar el access token sin pedir consentimiento de
 * nuevo. Al usarlo, se emite un access token nuevo y un refresh token nuevo,
 * y el actual queda revocado (rotación, recomendado por OAuth 2.1).
 */
#[Fillable([
    'token_hash',
    'oauth_client_id',
    'user_id',
    'household_id',
    'oauth_access_token_id',
    'scope',
    'expires_at',
    'revoked_at',
])]
class OAuthRefreshToken extends Model
{
    protected $table = 'oauth_refresh_tokens';

    public const PLAIN_PREFIX = 'finlia_ort_';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
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

    public function accessToken(): BelongsTo
    {
        return $this->belongsTo(OAuthAccessToken::class, 'oauth_access_token_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at')->where('expires_at', '>', now());
    }

    public function isUsable(): bool
    {
        return $this->revoked_at === null && $this->expires_at->isFuture();
    }
}
