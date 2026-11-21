<?php

declare(strict_types=1);

namespace App\Services\Mcp;

use RuntimeException;

/**
 * Error reportable como JSON-RPC: lleva el código y el mensaje que verá el
 * cliente. Se lanza desde una tool o dispatcher cuando hay que cortar sin
 * exponer trazas internas.
 */
class McpException extends RuntimeException
{
    public function __construct(private readonly int $jsonRpcCode, string $message)
    {
        parent::__construct($message);
    }

    public function jsonRpcCode(): int
    {
        return $this->jsonRpcCode;
    }
}
