<?php

declare(strict_types=1);

namespace App\Services\Mcp;

use App\Models\Household;
use App\Models\User;
use App\Services\Mcp\Tools\McpTool;
use Throwable;

/**
 * Despachador JSON-RPC 2.0 de las peticiones MCP (ADR-0049).
 *
 * MCP usa JSON-RPC 2.0 sobre un transporte. Aquí el transporte es HTTP
 * "streamable" simplificado: una respuesta completa por petición, suficiente
 * para tools de solo lectura y compatible con hosting compartido (sin SSE
 * ni procesos persistentes). Los métodos cubiertos en este primer PR:
 *
 *  - initialize
 *  - tools/list
 *  - tools/call
 *  - ping
 *
 * Seam (ADR-0010): este servicio no sabe de la capa HTTP. Recibe el payload
 * decodificado y devuelve otro array JSON-RPC; el controlador se encarga de
 * leer el body y de serializar la respuesta.
 */
class McpServer
{
    public const PROTOCOL_VERSION = '2025-06-18';

    public const JSON_RPC_VERSION = '2.0';

    private const ERROR_PARSE = -32700;

    private const ERROR_INVALID_REQUEST = -32600;

    private const ERROR_METHOD_NOT_FOUND = -32601;

    private const ERROR_INVALID_PARAMS = -32602;

    private const ERROR_INTERNAL = -32603;

    private const ERROR_FORBIDDEN = -32001;

    /** @var array<string, McpTool> */
    private array $tools;

    /**
     * @param  iterable<McpTool>  $tools
     */
    public function __construct(iterable $tools)
    {
        $this->tools = [];
        foreach ($tools as $tool) {
            $this->tools[$tool->name()] = $tool;
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function handle(array $payload, User $user, Household $household, McpAuthorization $token): array
    {
        $id = $payload['id'] ?? null;

        if (($payload['jsonrpc'] ?? null) !== self::JSON_RPC_VERSION) {
            return $this->error($id, self::ERROR_INVALID_REQUEST, 'jsonrpc debe ser "2.0"');
        }

        $method = $payload['method'] ?? null;
        if (! is_string($method) || $method === '') {
            return $this->error($id, self::ERROR_INVALID_REQUEST, 'method requerido');
        }

        $params = $payload['params'] ?? [];
        if (! is_array($params)) {
            return $this->error($id, self::ERROR_INVALID_PARAMS, 'params debe ser un objeto');
        }

        try {
            return match ($method) {
                'initialize' => $this->success($id, $this->initialize()),
                'ping' => $this->success($id, []),
                'notifications/initialized' => $this->notification(),
                'tools/list' => $this->success($id, $this->listTools($token)),
                'tools/call' => $this->success($id, $this->callTool($params, $user, $household, $token)),
                default => $this->error($id, self::ERROR_METHOD_NOT_FOUND, "Método no reconocido: {$method}"),
            };
        } catch (McpException $e) {
            return $this->error($id, $e->jsonRpcCode(), $e->getMessage());
        } catch (Throwable $e) {
            report($e);

            return $this->error($id, self::ERROR_INTERNAL, 'Error interno del servidor MCP');
        }
    }

    /** @return array<string, mixed> */
    private function initialize(): array
    {
        return [
            'protocolVersion' => self::PROTOCOL_VERSION,
            'serverInfo' => [
                'name' => 'finlia-mcp',
                'version' => (string) config('finlia.version', '0.0.0'),
            ],
            'capabilities' => [
                'tools' => ['listChanged' => false],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function listTools(McpAuthorization $token): array
    {
        $tools = [];
        foreach ($this->tools as $tool) {
            if (! $token->hasAbility($tool->requiredAbility())) {
                continue;
            }
            $tools[] = [
                'name' => $tool->name(),
                'title' => $tool->title(),
                'description' => $tool->description(),
                'inputSchema' => $tool->inputSchema(),
            ];
        }

        return ['tools' => $tools];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function callTool(array $params, User $user, Household $household, McpAuthorization $token): array
    {
        $name = $params['name'] ?? null;
        if (! is_string($name) || ! isset($this->tools[$name])) {
            throw new McpException(self::ERROR_METHOD_NOT_FOUND, 'Tool no encontrada');
        }

        $tool = $this->tools[$name];
        if (! $token->hasAbility($tool->requiredAbility())) {
            throw new McpException(self::ERROR_FORBIDDEN, 'El token no tiene permiso para invocar esta tool');
        }

        $arguments = $params['arguments'] ?? [];
        if (! is_array($arguments)) {
            throw new McpException(self::ERROR_INVALID_PARAMS, 'arguments debe ser un objeto');
        }

        $result = $tool->run($arguments, $user, $household);

        $json = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return [
            'content' => [[
                'type' => 'text',
                'text' => $json,
            ]],
            'structuredContent' => $result,
            'isError' => false,
        ];
    }

    /** @return array<string, mixed> */
    private function success(mixed $id, array $result): array
    {
        return [
            'jsonrpc' => self::JSON_RPC_VERSION,
            'id' => $id,
            'result' => $result,
        ];
    }

    /** @return array<string, mixed> */
    private function error(mixed $id, int $code, string $message): array
    {
        return [
            'jsonrpc' => self::JSON_RPC_VERSION,
            'id' => $id,
            'error' => ['code' => $code, 'message' => $message],
        ];
    }

    /** @return array{notification: true} */
    private function notification(): array
    {
        return ['notification' => true];
    }

    public static function parseError(mixed $id = null): array
    {
        return [
            'jsonrpc' => self::JSON_RPC_VERSION,
            'id' => $id,
            'error' => ['code' => self::ERROR_PARSE, 'message' => 'JSON mal formado'],
        ];
    }
}
