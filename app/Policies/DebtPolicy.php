<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Debt;
use App\Models\User;
use App\Policies\Concerns\ChecksHouseholdAccess;

/**
 * Autorización sobre deudas. Aislamiento multi-hogar (amenaza #1): todo
 * acceso exige ser miembro del hogar dueño de la deuda Y que ese hogar sea
 * el activo (ver ChecksHouseholdAccess).
 */
class DebtPolicy
{
    use ChecksHouseholdAccess;

    public function viewAny(User $user): bool
    {
        return $this->canAccessActiveHousehold($user);
    }

    public function view(User $user, Debt $debt): bool
    {
        return $this->canAccessHousehold($user, $debt->household_id);
    }

    public function create(User $user): bool
    {
        return $this->canAccessActiveHousehold($user);
    }

    public function update(User $user, Debt $debt): bool
    {
        return $this->canAccessHousehold($user, $debt->household_id);
    }

    public function delete(User $user, Debt $debt): bool
    {
        return $this->canAccessHousehold($user, $debt->household_id);
    }

    /** Registrar un pago es una escritura (puede crear un gasto): mismo permiso. */
    public function pay(User $user, Debt $debt): bool
    {
        return $this->canAccessHousehold($user, $debt->household_id);
    }

    /** Registrar una refinanciación cambia las condiciones: mismo permiso. */
    public function refinance(User $user, Debt $debt): bool
    {
        return $this->canAccessHousehold($user, $debt->household_id);
    }
}
