<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Transfer;
use App\Models\User;
use App\Policies\Concerns\ChecksHouseholdAccess;

/**
 * Autorización sobre transferencias entre cuentas (Épica 10, ADR-0035).
 * Aislamiento multi-hogar: amenaza #1 de docs/SECURITY.md.
 */
class TransferPolicy
{
    use ChecksHouseholdAccess;

    public function view(User $user, Transfer $transfer): bool
    {
        return $this->canAccessHousehold($user, $transfer->household_id);
    }

    public function create(User $user): bool
    {
        return $this->canAccessActiveHousehold($user);
    }

    public function update(User $user, Transfer $transfer): bool
    {
        return $this->canAccessHousehold($user, $transfer->household_id);
    }

    public function delete(User $user, Transfer $transfer): bool
    {
        return $this->canAccessHousehold($user, $transfer->household_id);
    }
}
