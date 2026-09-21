<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Receivable;
use App\Models\User;
use App\Policies\Concerns\ChecksHouseholdAccess;

/**
 * Autorización sobre cuentas por cobrar (Épica 15). Aislamiento
 * multi-hogar (amenaza #1): todo acceso exige ser miembro del hogar
 * dueño de la cuenta por cobrar Y que ese hogar sea el activo.
 */
class ReceivablePolicy
{
    use ChecksHouseholdAccess;

    public function viewAny(User $user): bool
    {
        return $this->canAccessActiveHousehold($user);
    }

    public function view(User $user, Receivable $receivable): bool
    {
        return $this->canAccessHousehold($user, $receivable->household_id);
    }

    public function create(User $user): bool
    {
        return $this->canAccessActiveHousehold($user);
    }

    public function update(User $user, Receivable $receivable): bool
    {
        return $this->canAccessHousehold($user, $receivable->household_id);
    }

    public function delete(User $user, Receivable $receivable): bool
    {
        return $this->canAccessHousehold($user, $receivable->household_id);
    }

    /** Registrar un cobro es una escritura (puede crear un ingreso): mismo permiso. */
    public function collect(User $user, Receivable $receivable): bool
    {
        return $this->canAccessHousehold($user, $receivable->household_id);
    }
}
