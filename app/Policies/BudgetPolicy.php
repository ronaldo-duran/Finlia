<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Budget;
use App\Models\User;

/**
 * Autorización sobre presupuestos. Aislamiento multi-hogar (amenaza #1):
 * todo acceso requiere ser miembro del hogar dueño del presupuesto.
 */
class BudgetPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->hasActiveHousehold($user);
    }

    public function view(User $user, Budget $budget): bool
    {
        return $this->userInHousehold($user, $budget->household_id);
    }

    public function create(User $user): bool
    {
        return $this->hasActiveHousehold($user);
    }

    public function update(User $user, Budget $budget): bool
    {
        return $this->userInHousehold($user, $budget->household_id);
    }

    public function delete(User $user, Budget $budget): bool
    {
        return $this->userInHousehold($user, $budget->household_id);
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
