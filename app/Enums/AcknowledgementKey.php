<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Avisos que el usuario puede dar por leídos (ADR-0024).
 *
 * Es una lista cerrada a propósito: la clave llega en la URL, así que sin
 * enum cualquiera podría llenar la tabla de filas inventadas.
 */
enum AcknowledgementKey: string
{
    /** Las cifras de deuda son estimaciones y varían según la entidad. */
    case DebtEstimates = 'debt_estimates';
}
