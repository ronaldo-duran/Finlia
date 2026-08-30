<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Origen de un recordatorio (Épica 9, ADR-0027).
 *
 * Los tres primeros se DERIVAN en vivo de su fuente (no existen como
 * filas): solo `custom` vive en la tabla `reminders`. La vista usa este
 * enum para enlazar cada ítem a su acción ("Marcar pagado", "Registrar
 * pago", "Ver meta") sin que el Service toque rutas (ADR-0010).
 */
enum ReminderSource: string
{
    case RecurringExpense = 'recurring_expense';
    case Debt = 'debt';
    case SavingsGoal = 'savings_goal';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::RecurringExpense => 'Gasto recurrente',
            self::Debt => 'Deuda',
            self::SavingsGoal => 'Meta de ahorro',
            self::Custom => 'Recordatorio',
        };
    }

    /** Icono de Bootstrap Icons para la lista unificada. */
    public function icon(): string
    {
        return match ($this) {
            self::RecurringExpense => 'bi-arrow-repeat',
            self::Debt => 'bi-credit-card-2-front',
            self::SavingsGoal => 'bi-piggy-bank',
            self::Custom => 'bi-bell',
        };
    }
}
