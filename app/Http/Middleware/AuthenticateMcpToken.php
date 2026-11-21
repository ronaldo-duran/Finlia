<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\McpToken;
use App\Models\OAuthAccessToken;
use App\Services\Mcp\McpTokenService;
use App\Services\Mcp\OAuthService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autentica una petición MCP contra un token personal o un access token OAuth.
 *
 * El hogar lo FIJA el token (PAT o OAuth), no el cliente: cualquier
 * referencia a hogar en el payload MCP se ignora. Si el usuario dejó de
 * pertenecer al hogar o el hogar fue borrado, el token deja de servir.
 *
 * En 401 se adjunta `WWW-Authenticate: Bearer resource_metadata="..."` para
 * que los clientes MCP descubran el authorization server (RFC 9728).
 */
class AuthenticateMcpToken
{
    public function __construct(
        private readonly McpTokenService $pats,
        private readonly OAuthService $oauth,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $header = (string) $request->header('Authorization', '');
        $plain = str_starts_with($header, 'Bearer ') ? substr($header, 7) : $header;

        if ($plain === '') {
            return $this->unauthorized('Falta el header Authorization: Bearer finlia_...');
        }

        if (str_starts_with($plain, McpToken::PLAIN_PREFIX)) {
            return $this->handlePat($request, $plain, $next);
        }

        if (str_starts_with($plain, OAuthAccessToken::PLAIN_PREFIX)) {
            return $this->handleOauth($request, $plain, $next);
        }

        return $this->unauthorized('Formato de token no reconocido');
    }

    private function handlePat(Request $request, string $plain, Closure $next): Response
    {
        $token = $this->pats->findActive($plain);
        if ($token === null) {
            return $this->unauthorized('Token inválido, revocado o vencido');
        }

        $user = $token->user;
        $household = $token->household;

        if ($user === null || $household === null) {
            return $this->unauthorized('El usuario o el hogar del token ya no existen');
        }

        if (! $user->households()->whereKey($household->id)->exists()) {
            return $this->unauthorized('El usuario ya no pertenece al hogar de este token');
        }

        app()->instance('finlia.active_household', $household);
        $request->setUserResolver(fn () => $user);
        $request->attributes->set('mcp_token', $token);
        $request->attributes->set('mcp_household', $household);

        $response = $next($request);

        $this->pats->touch($token);

        return $response;
    }

    private function handleOauth(Request $request, string $plain, Closure $next): Response
    {
        $token = $this->oauth->findActiveAccessToken($plain);
        if ($token === null) {
            return $this->unauthorized('Access token inválido, revocado o vencido');
        }

        $user = $token->user;
        $household = $token->household;

        if ($user === null || $household === null) {
            return $this->unauthorized('El usuario o el hogar del token ya no existen');
        }

        if (! $user->households()->whereKey($household->id)->exists()) {
            return $this->unauthorized('El usuario ya no pertenece al hogar de este token');
        }

        app()->instance('finlia.active_household', $household);
        $request->setUserResolver(fn () => $user);
        $request->attributes->set('mcp_token', $token);
        $request->attributes->set('mcp_household', $household);

        $response = $next($request);

        $this->oauth->touchAccessToken($token);

        return $response;
    }

    private function unauthorized(string $message): Response
    {
        return response()->json([
            'jsonrpc' => '2.0',
            'id' => null,
            'error' => ['code' => -32000, 'message' => $message],
        ], 401)->header(
            'WWW-Authenticate',
            'Bearer resource_metadata="'.url('/.well-known/oauth-protected-resource').'"',
        );
    }
}
