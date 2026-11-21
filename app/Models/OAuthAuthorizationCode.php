<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Código de autorización emitido tras el consentimiento del usuario.
 *
 * Es un credential de un solo uso con vida corta (60 segundos) que el
 * cliente intercambia por un access_token en /oauth/token. Guardamos un hash
 * SHA-256; el código en claro solo existe en la URL de redirección.
 */
#[Fillable([
    'code_hash',
    'oauth_client_id',
    'user_id',
    'household_id',
    'redirect_uri',
    'code_challenge',
    'code_challenge_method',
    'scope',
    'expires_at',
    'used_at',
])]
class OAuthAuthorizationCode extends Model
{
    protected $table = 'oauth_authorization_codes';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
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

    public function scopeUsable(Builder $query): Builder
    {
        return $query->whereNull('used_at')->where('expires_at', '>', now());
    }

    public function isUsable(): bool
    {
        return $this->used_at === null && $this->expires_at->isFuture();
    }
}
