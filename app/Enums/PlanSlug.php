<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Planes canónicos de Finlia (Épica 12).
 *
 * El slug es la fuente de verdad en código; la fila de `plans` en la BD lo
 * respeta y por eso puede consultarse por él sin miedo a que el nombre
 * humano cambie.
 */
enum PlanSlug: string
{
    case Free = 'free';
    case Premium = 'premium';

    public function label(): string
    {
        return match ($this) {
            self::Free => 'Gratis',
            self::Premium => 'Premium',
        };
    }

    /** Plan al que se asigna cualquier hogar recién creado. */
    public static function default(): self
    {
        return self::Free;
    }
}
