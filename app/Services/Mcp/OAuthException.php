<?php

declare(strict_types=1);

namespace App\Services\Mcp;

use RuntimeException;

/**
 * Error normativo de OAuth 2.1 (RFC 6749 §5.2 + extensiones).
 *
 * Lleva el código canónico (p. ej. "invalid_grant", "invalid_client") que el
 * controlador devuelve como `error` en el JSON del endpoint, y un status HTTP
 * opcional para los casos en que el estándar exige 401.
 */
class OAuthException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $description,
        public readonly int $httpStatus = 400,
    ) {
        parent::__construct($description);
    }
}
