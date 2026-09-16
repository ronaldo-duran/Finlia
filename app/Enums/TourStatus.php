<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Cómo terminó una guía para un usuario (ADR-0045).
 *
 * Las dos cuentan como «vista»: ninguna vuelve a aparecer sola. La distinción
 * es para saber si una guía está mal escrita — muchas `Skipped` seguidas en
 * la misma guía significa que sobra o que aburre, y eso no se ve si solo se
 * guarda un booleano.
 */
enum TourStatus: string
{
    /** Llegó al último paso. */
    case Completed = 'completed';

    /** La cerró antes de terminar (botón saltar, Esc o clic fuera). */
    case Skipped = 'skipped';
}
