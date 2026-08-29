<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Formatos de exportación de reportes (Épica 8).
 *
 * La épica pide CSV ahora y "preparar la arquitectura para PDF después":
 * este enum ES esa preparación. La exportación se valida y se despacha por
 * formato, así que añadir PDF (Premium, Épica 12) es añadir un caso y su
 * rama en el despachador, sin rediseñar nada.
 */
enum ReportFormat: string
{
    case Csv = 'csv';

    /** Etiqueta para la UI. */
    public function label(): string
    {
        return match ($this) {
            self::Csv => 'CSV',
        };
    }
}
