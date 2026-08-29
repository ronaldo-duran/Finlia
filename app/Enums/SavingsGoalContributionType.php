<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Tipo de movimiento sobre una meta de ahorro (Épica 7).
 *
 * `amount` se guarda siempre positivo: la dirección la da el tipo, igual
 * que en los pagos de deuda.
 */
enum SavingsGoalContributionType: string
{
    case Deposit = 'deposit';
    case Withdrawal = 'withdrawal';

    public function label(): string
    {
        return match ($this) {
            self::Deposit => 'Aporte',
            self::Withdrawal => 'Retiro',
        };
    }

    /** Icono Bootstrap Icons para el historial. */
    public function icon(): string
    {
        return match ($this) {
            self::Deposit => 'bi-arrow-down-left',
            self::Withdrawal => 'bi-arrow-up-right',
        };
    }

    /**
     * Signo con el que el movimiento afecta `current_amount`.
     */
    public function sign(): int
    {
        return match ($this) {
            self::Deposit => 1,
            self::Withdrawal => -1,
        };
    }
}
