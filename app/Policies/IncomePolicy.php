<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Income;
use App\Models\User;
use App\Policies\Concerns\ChecksHouseholdAccess;

/**
 * Autorización sobre ingresos. Aislamiento multi-hogar (amenaza #1):
 * todo acceso requiere ser miembro del hogar dueño del ingreso Y que ese
 * hogar sea el activo (ver ChecksHouseholdAccess).
 */
class IncomePolicy
{
    use ChecksHouseholdAccess;

    public function view(User $user, Income $income): bool
    {
        return $this->canAccessHousehold($user, $income->household_id);
    }

    public function create(User $user): bool
    {
        return $this->canAccessActiveHousehold($user);
    }

    public function update(User $user, Income $income): bool
    {
        return $this->canAccessHousehold($user, $income->household_id);
    }

    public function delete(User $user, Income $income): bool
    {
        return $this->canAccessHousehold($user, $income->household_id);
    }
}
