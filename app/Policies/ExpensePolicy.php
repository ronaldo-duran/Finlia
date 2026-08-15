<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Expense;
use App\Models\User;

/**
 * Autorización sobre gastos. Aislamiento multi-hogar (amenaza #1):
 * todo acceso requiere ser miembro del hogar dueño del gasto.
 */
class ExpensePolicy
{
    public function view(User $user, Expense $expense): bool
    {
        return $this->userInHousehold($user, $expense->household_id);
    }

    public function create(User $user): bool
    {
        $household = active_household();

        return $household !== null && $household->hasMember($user);
    }

    public function update(User $user, Expense $expense): bool
    {
        return $this->userInHousehold($user, $expense->household_id);
    }

    public function delete(User $user, Expense $expense): bool
    {
        return $this->userInHousehold($user, $expense->household_id);
    }

    private function userInHousehold(User $user, int $householdId): bool
    {
        return $user->households()->where('households.id', $householdId)->exists();
    }
}
