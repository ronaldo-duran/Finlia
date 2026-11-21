<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Household;
use App\Services\Mcp\McpAuthorization;
use App\Services\Mcp\McpServer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoint único JSON-RPC 2.0 del servidor MCP de Finlia (ADR-0049).
 *
 * El middleware mcp.auth ya autenticó al usuario y fijó el hogar; aquí solo
 * leemos el payload, lo pasamos al dispatcher y devolvemos su respuesta. Las
 * notificaciones (sin id, p. ej. notifications/initialized) responden 202
 * sin cuerpo, como manda JSON-RPC.
 */
class McpController extends Controller
{
    public function __invoke(Request $request, McpServer $server): JsonResponse
    {
        $raw = $request->getContent();
        $payload = json_decode($raw, true);

        if (! is_array($payload)) {
            return response()->json(McpServer::parseError(), 400);
        }

        $token = $request->attributes->get('mcp_token');
        $household = $request->attributes->get('mcp_household');

        if (! $token instanceof McpAuthorization || ! $household instanceof Household) {
            return response()->json(McpServer::parseError(), 500);
        }

        $response = $server->handle($payload, $request->user(), $household, $token);

        if (isset($response['notification']) && $response['notification'] === true) {
            return response()->json(null, 202);
        }

        return response()->json($response);
    }
}
