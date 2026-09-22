<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Estado de una cuenta por cobrar (Épica 15). Espejo estructural de
 * DebtStatus pero sin refinanciación: cuando alguien te debe, o te paga,
 * o lo das por perdido.
 */
enum ReceivableStatus: string
{
    case Pending = 'pending';
    case Partial = 'partial';
    case Paid = 'paid';
    case WrittenOff = 'written_off';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente',
            self::Partial => 'Cobrado en parte',
            self::Paid => 'Cobrado',
            self::WrittenOff => 'Dado por perdido',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Pending => 'text-bg-primary',
            self::Partial => 'text-bg-warning',
            self::Paid => 'text-bg-success',
            self::WrittenOff => 'text-bg-secondary',
        };
    }

    /**
     * ¿Sigue pesando a favor del hogar? Pendiente y parcial sí; cobrada
     * y dada por perdida no. Es el filtro del panel y de la tarjeta del
     * dashboard.
     */
    public function isOutstanding(): bool
    {
        return $this === self::Pending || $this === self::Partial;
    }

    /**
     * @return array<int, string>
     */
    public static function outstandingValues(): array
    {
        return array_values(array_map(
            static fn (self $case) => $case->value,
            array_filter(self::cases(), static fn (self $case) => $case->isOutstanding()),
        ));
    }
}
