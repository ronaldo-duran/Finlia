<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Tipo de categoría: ingreso o gasto.
 */
enum CategoryType: string
{
    case Income = 'income';
    case Expense = 'expense';

    public function label(): string
    {
        return match ($this) {
            self::Income => 'Ingreso',
            self::Expense => 'Gasto',
        };
    }
}
