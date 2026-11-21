<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Mcp\McpTokenService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autentica una petición MCP contra un token personal (ADR-0049).
 *
 * El hogar lo FIJA el token, no el cliente: cualquier referencia a hogar en
 * el payload MCP se ignora. Si el usuario dejó de pertenecer al hogar o el
 * hogar fue borrado, el token deja de servir.
 */
class AuthenticateMcpToken
{
    public function __construct(private readonly McpTokenService $tokens) {}

    public function handle(Request $request, Closure $next): Response
    {
        $header = (string) $request->header('Authorization', '');
        $plain = str_starts_with($header, 'Bearer ') ? substr($header, 7) : $header;

        if ($plain === '') {
            return $this->unauthorized('Falta el header Authorization: Bearer finlia_mcp_...');
        }

        $token = $this->tokens->findActive($plain);
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

        $this->tokens->touch($token);

        return $response;
    }

    private function unauthorized(string $message): Response
    {
        return response()->json([
            'jsonrpc' => '2.0',
            'id' => null,
            'error' => ['code' => -32000, 'message' => $message],
        ], 401);
    }
}
