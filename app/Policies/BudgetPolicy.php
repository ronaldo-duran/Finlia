<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Budget;
use App\Models\User;
use App\Policies\Concerns\ChecksHouseholdAccess;

/**
 * Autorización sobre presupuestos. Aislamiento multi-hogar (amenaza #1):
 * todo acceso requiere ser miembro del hogar dueño del presupuesto Y que ese
 * hogar sea el activo (ver ChecksHouseholdAccess).
 */
class BudgetPolicy
{
    use ChecksHouseholdAccess;

    public function viewAny(User $user): bool
    {
        return $this->canAccessActiveHousehold($user);
    }

    public function view(User $user, Budget $budget): bool
    {
        return $this->canAccessHousehold($user, $budget->household_id);
    }

    public function create(User $user): bool
    {
        return $this->canAccessActiveHousehold($user);
    }

    public function update(User $user, Budget $budget): bool
    {
        return $this->canAccessHousehold($user, $budget->household_id);
    }

    public function delete(User $user, Budget $budget): bool
    {
        return $this->canAccessHousehold($user, $budget->household_id);
    }
}
