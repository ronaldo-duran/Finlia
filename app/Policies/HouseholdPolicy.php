<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Household;
use App\Models\User;

/**
 * Autorización sobre hogares. El aislamiento multi-hogar (amenaza #1) vive aquí:
 * todo acceso a un hogar requiere ser miembro; la gestión requiere ser owner.
 */
class HouseholdPolicy
{
    /**
     * Ver el detalle / configuración del hogar.
     */
    public function view(User $user, Household $household): bool
    {
        return $household->hasMember($user);
    }

    /**
     * Listar / crear hogares: cualquier usuario autenticado.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Editar datos del hogar: solo el administrador.
     */
    public function update(User $user, Household $household): bool
    {
        return $this->isOwner($user, $household);
    }

    /**
     * Eliminar el hogar: solo el administrador.
     */
    public function delete(User $user, Household $household): bool
    {
        return $this->isOwner($user, $household);
    }

    /**
     * Gestionar miembros (invitar / expulsar): solo el administrador.
     */
    public function manageMembers(User $user, Household $household): bool
    {
        return $this->isOwner($user, $household);
    }

    /**
     * Enviar invitaciones: solo el administrador.
     */
    public function invite(User $user, Household $household): bool
    {
        return $this->isOwner($user, $household);
    }

    /**
     * Activar (cambiar) el hogar activo: cualquier miembro puede elegir el suyo.
     */
    public function activate(User $user, Household $household): bool
    {
        return $household->hasMember($user);
    }

    private function isOwner(User $user, Household $household): bool
    {
        return $household->owner_id === $user->id;
    }
}
