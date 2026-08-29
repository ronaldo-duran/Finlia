<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\RecurringExpense;
use App\Models\User;
use App\Policies\Concerns\ChecksHouseholdAccess;

/**
 * Autorización sobre gastos recurrentes. Aislamiento multi-hogar (amenaza #1):
 * todo acceso requiere ser miembro del hogar dueño del registro Y que ese
 * hogar sea el activo (ver ChecksHouseholdAccess).
 */
class RecurringExpensePolicy
{
    use ChecksHouseholdAccess;

    public function viewAny(User $user): bool
    {
        return $this->canAccessActiveHousehold($user);
    }

    public function view(User $user, RecurringExpense $recurringExpense): bool
    {
        return $this->canAccessHousehold($user, $recurringExpense->household_id);
    }

    public function create(User $user): bool
    {
        return $this->canAccessActiveHousehold($user);
    }

    public function update(User $user, RecurringExpense $recurringExpense): bool
    {
        return $this->canAccessHousehold($user, $recurringExpense->household_id);
    }

    public function delete(User $user, RecurringExpense $recurringExpense): bool
    {
        return $this->canAccessHousehold($user, $recurringExpense->household_id);
    }

    /**
     * "Marcar pagado" es una escritura más (crea un gasto): mismo permiso.
     */
    public function markPaid(User $user, RecurringExpense $recurringExpense): bool
    {
        return $this->canAccessHousehold($user, $recurringExpense->household_id);
    }
}
