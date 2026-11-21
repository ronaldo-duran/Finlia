<?php

declare(strict_types=1);

namespace App\Services\Mcp\Tools;

use App\Models\Household;
use App\Models\User;

/**
 * Contrato común de una tool MCP (ADR-0049).
 *
 * Cada tool es un adaptador fino sobre un Service de dominio. El servidor MCP
 * se encarga de autenticar y resolver el hogar; aquí llega todo ya autorizado.
 */
interface McpTool
{
    public function name(): string;

    public function title(): string;

    public function description(): string;

    /**
     * Habilidad del token exigida para invocar esta tool.
     */
    public function requiredAbility(): string;

    /**
     * Esquema JSON Schema del input, tal como MCP lo expone en `tools/list`.
     *
     * @return array<string, mixed>
     */
    public function inputSchema(): array;

    /**
     * Ejecuta la tool. El resultado se envuelve en el bloque `content`
     * estándar de MCP a nivel del dispatcher.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function run(array $arguments, User $user, Household $household): array;
}
