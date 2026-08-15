<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Income;
use App\Models\User;

/**
 * Autorización sobre ingresos. Aislamiento multi-hogar (amenaza #1):
 * todo acceso requiere ser miembro del hogar dueño del ingreso.
 */
class IncomePolicy
{
    public function view(User $user, Income $income): bool
    {
        return $this->userInHousehold($user, $income->household_id);
    }

    public function create(User $user): bool
    {
        $household = active_household();

        return $household !== null && $household->hasMember($user);
    }

    public function update(User $user, Income $income): bool
    {
        return $this->userInHousehold($user, $income->household_id);
    }

    public function delete(User $user, Income $income): bool
    {
        return $this->userInHousehold($user, $income->household_id);
    }

    private function userInHousehold(User $user, int $householdId): bool
    {
        return $user->households()->where('households.id', $householdId)->exists();
    }
}
