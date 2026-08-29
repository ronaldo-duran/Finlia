<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Estado de una deuda (Épica 6).
 */
enum DebtStatus: string
{
    case Active = 'active';
    case Refinanced = 'refinanced';
    case Paid = 'paid';
    case WrittenOff = 'written_off';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Activa',
            self::Refinanced => 'Refinanciada',
            self::Paid => 'Pagada',
            self::WrittenOff => 'Condonada',
        };
    }

    /**
     * Clase de color Bootstrap para el badge de estado.
     */
    public function badgeClass(): string
    {
        return match ($this) {
            self::Active => 'text-bg-primary',
            self::Refinanced => 'text-bg-warning',
            self::Paid => 'text-bg-success',
            self::WrittenOff => 'text-bg-secondary',
        };
    }

    /**
     * ¿Sigue pesando en el bolsillo del hogar?
     *
     * Una deuda refinanciada SÍ pesa: cambió de condiciones, no desapareció.
     * Pagada y condonada no. Es el filtro del panel de deuda y del término
     * `debt` del dinero disponible (ADR-0014).
     */
    public function isOutstanding(): bool
    {
        return $this === self::Active || $this === self::Refinanced;
    }

    /**
     * Estados que siguen contando, para acotar consultas.
     *
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
