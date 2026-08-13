<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\HouseholdInvitation;
use App\Models\User;

/**
 * Autorización sobre invitaciones. Revocar requiere ser owner del hogar.
 * La aceptación se valida por token + coincidencia de correo en el Service.
 */
class HouseholdInvitationPolicy
{
    /**
     * Revocar / eliminar una invitación: solo el administrador del hogar.
     */
    public function delete(User $user, HouseholdInvitation $invitation): bool
    {
        return $invitation->household->owner_id === $user->id;
    }
}
