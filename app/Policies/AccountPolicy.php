<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Account;
use App\Models\User;

/**
 * Autorización sobre cuentas. Aislamiento multi-hogar (amenaza #1):
 * todo acceso requiere ser miembro del hogar dueño de la cuenta.
 */
class AccountPolicy
{
    public function view(User $user, Account $account): bool
    {
        return $this->userInHousehold($user, $account->household_id);
    }

    public function create(User $user): bool
    {
        $household = active_household();

        return $household !== null && $household->hasMember($user);
    }

    public function update(User $user, Account $account): bool
    {
        return $this->userInHousehold($user, $account->household_id);
    }

    public function delete(User $user, Account $account): bool
    {
        return $this->userInHousehold($user, $account->household_id);
    }

    private function userInHousehold(User $user, int $householdId): bool
    {
        return $user->households()->where('households.id', $householdId)->exists();
    }
}
