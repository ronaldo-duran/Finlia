<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Medio de pago de un gasto.
 */
enum PaymentMethod: string
{
    case Cash = 'cash';
    case DebitCard = 'debit_card';
    case CreditCard = 'credit_card';
    case Transfer = 'transfer';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Efectivo',
            self::DebitCard => 'Tarjeta débito',
            self::CreditCard => 'Tarjeta crédito',
            self::Transfer => 'Transferencia',
            self::Other => 'Otro',
        };
    }
}
