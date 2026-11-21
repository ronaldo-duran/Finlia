<?php

declare(strict_types=1);

namespace App\Http\Controllers\OAuth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Metadata OAuth 2.1 que los clientes MCP descubren automáticamente.
 *
 * - /.well-known/oauth-protected-resource (RFC 9728) apunta al authorization
 *   server que protege /mcp.
 * - /.well-known/oauth-authorization-server (RFC 8414) describe los endpoints
 *   y capacidades (PKCE S256 obligatorio, DCR abierto, scopes soportados).
 */
class DiscoveryController extends Controller
{
    public function protectedResource(): JsonResponse
    {
        $base = url('/');

        return response()->json([
            'resource' => $base.'/mcp',
            'authorization_servers' => [$base],
            'scopes_supported' => ['mcp:read'],
            'bearer_methods_supported' => ['header'],
            'resource_documentation' => $base.'/perfil/mcp',
        ])->header('Cache-Control', 'public, max-age=300');
    }

    public function authorizationServer(): JsonResponse
    {
        $base = url('/');

        return response()->json([
            'issuer' => $base,
            'authorization_endpoint' => $base.'/oauth/authorize',
            'token_endpoint' => $base.'/oauth/token',
            'registration_endpoint' => $base.'/oauth/register',
            'revocation_endpoint' => $base.'/oauth/revoke',
            'response_types_supported' => ['code'],
            'grant_types_supported' => ['authorization_code', 'refresh_token'],
            'code_challenge_methods_supported' => ['S256'],
            'token_endpoint_auth_methods_supported' => ['none'],
            'scopes_supported' => ['mcp:read'],
            'service_documentation' => $base.'/perfil/mcp',
        ])->header('Cache-Control', 'public, max-age=300');
    }
}
