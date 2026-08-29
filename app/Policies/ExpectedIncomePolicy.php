<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ExpectedIncome;
use App\Models\User;
use App\Policies\Concerns\ChecksHouseholdAccess;

/**
 * Autorización sobre ingresos esperados. Aislamiento multi-hogar (amenaza #1):
 * todo acceso requiere ser miembro del hogar dueño del registro Y que ese
 * hogar sea el activo (ver ChecksHouseholdAccess).
 */
class ExpectedIncomePolicy
{
    use ChecksHouseholdAccess;

    public function viewAny(User $user): bool
    {
        return $this->canAccessActiveHousehold($user);
    }

    public function view(User $user, ExpectedIncome $expectedIncome): bool
    {
        return $this->canAccessHousehold($user, $expectedIncome->household_id);
    }

    public function create(User $user): bool
    {
        return $this->canAccessActiveHousehold($user);
    }

    public function update(User $user, ExpectedIncome $expectedIncome): bool
    {
        return $this->canAccessHousehold($user, $expectedIncome->household_id);
    }

    public function delete(User $user, ExpectedIncome $expectedIncome): bool
    {
        return $this->canAccessHousehold($user, $expectedIncome->household_id);
    }
}
