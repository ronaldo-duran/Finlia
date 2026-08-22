<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ExpectedIncome;
use App\Models\User;

/**
 * Autorización sobre ingresos esperados. Aislamiento multi-hogar (amenaza #1):
 * todo acceso requiere ser miembro del hogar dueño del registro.
 */
class ExpectedIncomePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->hasActiveHousehold($user);
    }

    public function view(User $user, ExpectedIncome $expectedIncome): bool
    {
        return $this->userInHousehold($user, $expectedIncome->household_id);
    }

    public function create(User $user): bool
    {
        return $this->hasActiveHousehold($user);
    }

    public function update(User $user, ExpectedIncome $expectedIncome): bool
    {
        return $this->userInHousehold($user, $expectedIncome->household_id);
    }

    public function delete(User $user, ExpectedIncome $expectedIncome): bool
    {
        return $this->userInHousehold($user, $expectedIncome->household_id);
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
