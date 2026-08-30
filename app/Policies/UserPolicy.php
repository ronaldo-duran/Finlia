<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/**
 * Autorización sobre la cuenta propia (Plan 02). El perfil no es un
 * recurso del hogar: /perfil siempre opera sobre el usuario autenticado,
 * así que la regla es una sola — nadie toca la cuenta de otro usuario.
 */
class UserPolicy
{
    /**
     * Editar datos, contraseña o correo: solo el propio usuario.
     */
    public function update(User $user, User $target): bool
    {
        return $user->is($target);
    }
}
