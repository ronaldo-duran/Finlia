<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\SavingsGoal;
use App\Models\User;
use App\Policies\Concerns\ChecksHouseholdAccess;

/**
 * Autorización sobre metas de ahorro. Aislamiento multi-hogar (amenaza #1):
 * todo acceso exige ser miembro del hogar dueño de la meta Y que ese hogar
 * sea el activo (ver ChecksHouseholdAccess).
 */
class SavingsGoalPolicy
{
    use ChecksHouseholdAccess;

    public function viewAny(User $user): bool
    {
        return $this->canAccessActiveHousehold($user);
    }

    public function view(User $user, SavingsGoal $goal): bool
    {
        return $this->canAccessHousehold($user, $goal->household_id);
    }

    public function create(User $user): bool
    {
        return $this->canAccessActiveHousehold($user);
    }

    public function update(User $user, SavingsGoal $goal): bool
    {
        return $this->canAccessHousehold($user, $goal->household_id);
    }

    public function delete(User $user, SavingsGoal $goal): bool
    {
        return $this->canAccessHousehold($user, $goal->household_id);
    }

    /** Aportar o retirar es una escritura sobre la meta: mismo permiso. */
    public function contribute(User $user, SavingsGoal $goal): bool
    {
        return $this->canAccessHousehold($user, $goal->household_id);
    }
}
