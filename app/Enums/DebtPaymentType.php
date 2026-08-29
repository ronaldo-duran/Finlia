<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Naturaleza de un pago de deuda (Épica 6).
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
            self::Scheduled => 'Cuota pactada',
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
