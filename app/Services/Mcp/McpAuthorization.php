<?php

declare(strict_types=1);

namespace App\Services\Mcp;

/**
 * Contrato común para cualquier credencial que autoriza una petición MCP.
 *
 * Lo cumplen tanto los PAT (`App\Models\McpToken`) como los access tokens
 * emitidos por OAuth 2.1 (`App\Models\OAuthAccessToken`). McpServer habla
 * con este contrato para no conocer qué tipo de credencial trajo el usuario.
 */
interface McpAuthorization
{
    /**
     * ¿Esta credencial puede invocar tools que requieren la habilidad dada?
     * Para OAuth, el mapeo scope→ability lo resuelve la implementación.
     */
    public function hasAbility(string $ability): bool;
}
