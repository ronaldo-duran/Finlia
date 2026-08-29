<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Tipo de deuda (Épica 6).
 */
enum DebtType: string
{
    case CreditCard = 'credit_card';
    case Loan = 'loan';
    case Vehicle = 'vehicle';
    case Family = 'family';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::CreditCard => 'Tarjeta de crédito',
            self::Loan => 'Préstamo',
            self::Vehicle => 'Crédito de vehículo',
            self::Family => 'Préstamo familiar',
            self::Other => 'Otra',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::CreditCard => 'bi-credit-card',
            self::Loan => 'bi-bank',
            self::Vehicle => 'bi-car-front',
            self::Family => 'bi-people',
            self::Other => 'bi-cash-stack',
        };
    }

    /**
     * ¿Este tipo admite los atributos de tarjeta (cupo, corte, cuota de manejo)?
     */
    public function isCard(): bool
    {
        return $this === self::CreditCard;
    }
}
