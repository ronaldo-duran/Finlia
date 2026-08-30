<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Reminder;
use App\Models\User;
use App\Policies\Concerns\ChecksHouseholdAccess;

/**
 * Autorización sobre recordatorios sueltos. Aislamiento multi-hogar
 * (amenaza #1): todo acceso requiere ser miembro del hogar dueño del
 * registro Y que ese hogar sea el activo (ver ChecksHouseholdAccess).
 */
class ReminderPolicy
{
    use ChecksHouseholdAccess;

    public function viewAny(User $user): bool
    {
        return $this->canAccessActiveHousehold($user);
    }

    public function view(User $user, Reminder $reminder): bool
    {
        return $this->canAccessHousehold($user, $reminder->household_id);
    }

    public function create(User $user): bool
    {
        return $this->canAccessActiveHousehold($user);
    }

    public function update(User $user, Reminder $reminder): bool
    {
        return $this->canAccessHousehold($user, $reminder->household_id);
    }

    public function delete(User $user, Reminder $reminder): bool
    {
        return $this->canAccessHousehold($user, $reminder->household_id);
    }

    /**
     * "Completar" es una escritura más (avanza la fecha o cierra el aviso).
     */
    public function complete(User $user, Reminder $reminder): bool
    {
        return $this->canAccessHousehold($user, $reminder->household_id);
    }

    /**
     * Preferencia personal de digest por correo: cualquier miembro del
     * hogar activo decide sobre la suya (ADR-0028).
     */
    public function manageEmailPreferences(User $user): bool
    {
        return $this->canAccessActiveHousehold($user);
    }
}
