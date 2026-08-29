<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Account;
use App\Models\User;
use App\Policies\Concerns\ChecksHouseholdAccess;

/**
 * Autorización sobre cuentas. Aislamiento multi-hogar (amenaza #1):
 * todo acceso requiere ser miembro del hogar dueño de la cuenta Y que ese
 * hogar sea el activo (ver ChecksHouseholdAccess).
 */
class AccountPolicy
{
    use ChecksHouseholdAccess;

    public function view(User $user, Account $account): bool
    {
        return $this->canAccessHousehold($user, $account->household_id);
    }

    public function create(User $user): bool
    {
        return $this->canAccessActiveHousehold($user);
    }

    public function update(User $user, Account $account): bool
    {
        return $this->canAccessHousehold($user, $account->household_id);
    }

    public function delete(User $user, Account $account): bool
    {
        return $this->canAccessHousehold($user, $account->household_id);
    }
}
