<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Expense;
use App\Models\User;
use App\Policies\Concerns\ChecksHouseholdAccess;

/**
 * Autorización sobre gastos. Aislamiento multi-hogar (amenaza #1):
 * todo acceso requiere ser miembro del hogar dueño del gasto Y que ese hogar
 * sea el activo (ver ChecksHouseholdAccess).
 */
class ExpensePolicy
{
    use ChecksHouseholdAccess;

    public function view(User $user, Expense $expense): bool
    {
        return $this->canAccessHousehold($user, $expense->household_id);
    }

    public function create(User $user): bool
    {
        return $this->canAccessActiveHousehold($user);
    }

    public function update(User $user, Expense $expense): bool
    {
        return $this->canAccessHousehold($user, $expense->household_id);
    }

    public function delete(User $user, Expense $expense): bool
    {
        return $this->canAccessHousehold($user, $expense->household_id);
    }
}
