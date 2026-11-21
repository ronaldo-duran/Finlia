<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\OAuthClientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Cliente OAuth 2.1 registrado vía Dynamic Client Registration (RFC 7591).
 *
 * Los MCP de claude.ai y ChatGPT se registran automáticamente al añadir el
 * conector: envían client_name y redirect_uris, nosotros emitimos un
 * client_id público. Como usamos PKCE (RFC 7636) con S256, los clientes son
 * "públicos" y no manejamos client_secret: la seguridad del flujo recae en
 * el code_verifier que solo conoce el cliente.
 */
#[Fillable([
    'client_id',
    'client_name',
    'redirect_uris',
    'grant_types',
    'response_types',
    'token_endpoint_auth_method',
    'scope',
    'client_uri',
    'logo_uri',
    'software_id',
    'software_version',
])]
class OAuthClient extends Model
{
    /** @use HasFactory<OAuthClientFactory> */
    use HasFactory;

    protected $table = 'oauth_clients';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'redirect_uris' => 'array',
            'grant_types' => 'array',
            'response_types' => 'array',
        ];
    }

    public function authorizationCodes(): HasMany
    {
        return $this->hasMany(OAuthAuthorizationCode::class);
    }

    public function accessTokens(): HasMany
    {
        return $this->hasMany(OAuthAccessToken::class);
    }

    public function refreshTokens(): HasMany
    {
        return $this->hasMany(OAuthRefreshToken::class);
    }

    public function hasRedirectUri(string $uri): bool
    {
        return in_array($uri, (array) $this->redirect_uris, true);
    }
}
