<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ReceivablePayment;
use App\Models\User;
use App\Policies\Concerns\ChecksHouseholdAccess;

/**
 * Autorización sobre cobros de una cuenta por cobrar. Borrar un cobro
 * deshace un ingreso real, así que exige el mismo aislamiento que
 * cualquier otro movimiento.
 */
class ReceivablePaymentPolicy
{
    use ChecksHouseholdAccess;

    public function view(User $user, ReceivablePayment $receivablePayment): bool
    {
        return $this->canAccessHousehold($user, $receivablePayment->household_id);
    }

    public function delete(User $user, ReceivablePayment $receivablePayment): bool
    {
        return $this->canAccessHousehold($user, $receivablePayment->household_id);
    }
}
