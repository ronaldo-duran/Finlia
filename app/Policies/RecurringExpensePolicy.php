<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\RecurringExpense;
use App\Models\User;

/**
 * Autorización sobre gastos recurrentes. Aislamiento multi-hogar (amenaza #1):
 * todo acceso requiere ser miembro del hogar dueño del registro.
 */
class RecurringExpensePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->hasActiveHousehold($user);
    }

    public function view(User $user, RecurringExpense $recurringExpense): bool
    {
        return $this->userInHousehold($user, $recurringExpense->household_id);
    }

    public function create(User $user): bool
    {
        return $this->hasActiveHousehold($user);
    }

    public function update(User $user, RecurringExpense $recurringExpense): bool
    {
        return $this->userInHousehold($user, $recurringExpense->household_id);
    }

    public function delete(User $user, RecurringExpense $recurringExpense): bool
    {
        return $this->userInHousehold($user, $recurringExpense->household_id);
    }

    /**
     * "Marcar pagado" es una escritura más (crea un gasto): mismo permiso.
     */
    public function markPaid(User $user, RecurringExpense $recurringExpense): bool
    {
        return $this->userInHousehold($user, $recurringExpense->household_id);
    }

    private function hasActiveHousehold(User $user): bool
    {
        $household = active_household();

        return $household !== null && $household->hasMember($user);
    }

    private function userInHousehold(User $user, int $householdId): bool
    {
        return $user->households()->where('households.id', $householdId)->exists();
    }
}
