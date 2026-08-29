<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CreditCard;
use App\Models\User;
use App\Policies\Concerns\ChecksHouseholdAccess;

/**
 * Autorización sobre los atributos de tarjeta (cupo, corte, cuota de manejo).
 */
class CreditCardPolicy
{
    use ChecksHouseholdAccess;

    public function view(User $user, CreditCard $creditCard): bool
    {
        return $this->canAccessHousehold($user, $creditCard->household_id);
    }

    public function update(User $user, CreditCard $creditCard): bool
    {
        return $this->canAccessHousehold($user, $creditCard->household_id);
    }

    public function delete(User $user, CreditCard $creditCard): bool
    {
        return $this->canAccessHousehold($user, $creditCard->household_id);
    }
}
