<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\DebtPayment;
use App\Models\User;
use App\Policies\Concerns\ChecksHouseholdAccess;

/**
 * Autorización sobre pagos de deuda. Borrar un pago deshace un gasto real,
 * así que exige el mismo aislamiento que cualquier otro movimiento.
 */
class DebtPaymentPolicy
{
    use ChecksHouseholdAccess;

    public function view(User $user, DebtPayment $debtPayment): bool
    {
        return $this->canAccessHousehold($user, $debtPayment->household_id);
    }

    public function delete(User $user, DebtPayment $debtPayment): bool
    {
        return $this->canAccessHousehold($user, $debtPayment->household_id);
    }
}
