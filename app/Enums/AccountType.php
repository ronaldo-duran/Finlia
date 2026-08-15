<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Tipo de cuenta financiera.
 */
enum AccountType: string
{
    case Cash = 'cash';
    case Bank = 'bank';
    case Savings = 'savings';
    case DigitalWallet = 'digital_wallet';
    case CreditCard = 'credit_card';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Efectivo',
            self::Bank => 'Cuenta bancaria',
            self::Savings => 'Cuenta de ahorros',
            self::DigitalWallet => 'Billetera digital',
            self::CreditCard => 'Tarjeta de crédito',
            self::Other => 'Otro',
        };
    }

    /**
     * Icono de Bootstrap Icons para el tipo.
     */
    public function icon(): string
    {
        return match ($this) {
            self::Cash => 'bi-cash-coin',
            self::Bank => 'bi-bank',
            self::Savings => 'bi-piggy-bank',
            self::DigitalWallet => 'bi-wallet2',
            self::CreditCard => 'bi-credit-card',
            self::Other => 'bi-wallet',
        };
    }
}
