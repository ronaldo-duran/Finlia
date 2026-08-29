<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Naturaleza de un pago de deuda (Épica 6).
 *
 * Las etiquetas hablan el mismo idioma que el formulario de la deuda tras
 * ADR-0022: allí el campo obligatorio se llama «cuota mensual», así que un
 * pago de esa cantidad es una «cuota mensual», no una «cuota pactada» —
 * nombre que desapareció con el renombrado.
 *
 * Los VALORES del enum no cambian (`scheduled` sigue siendo `scheduled`):
 * renombrarlos obligaría a migrar los pagos ya registrados sin ganar nada.
 */
enum DebtPaymentType: string
{
    case Minimum = 'minimum';
    case Scheduled = 'scheduled';
    case Extra = 'extra';

    public function label(): string
    {
        return match ($this) {
            self::Minimum => 'Pago mínimo',
            self::Scheduled => 'Cuota mensual',
            self::Extra => 'Abono extra',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Minimum => 'text-bg-warning',
            self::Scheduled => 'text-bg-secondary',
            self::Extra => 'text-bg-success',
        };
    }
}
