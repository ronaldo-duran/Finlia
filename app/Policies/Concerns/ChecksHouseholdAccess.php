<?php

declare(strict_types=1);

namespace App\Policies\Concerns;

use App\Models\User;

/**
 * Comprobaciones de acceso comunes a todo recurso financiero de un hogar
 * (cuentas, categorías, ingresos, gastos, presupuestos, ingresos esperados,
 * recurrentes). Aislamiento multi-hogar: amenaza #1 de docs/SECURITY.md.
 *
 * NO lo usan HouseholdPolicy ni HouseholdInvitationPolicy: gestionar un hogar
 * (renombrarlo, invitar, activarlo) tiene que poder hacerse desde fuera del
 * hogar activo, o no habría forma de cambiar de hogar.
 */
trait ChecksHouseholdAccess
{
    /**
     * ¿El usuario puede operar sobre un recurso de este hogar?
     *
     * Exige **dos** condiciones, no una:
     *
     *  1. que sea miembro del hogar dueño del recurso, y
     *  2. que ese hogar sea además su **hogar activo**.
     *
     * La segunda no es redundante. Un usuario puede pertenecer a varios
     * hogares (ADR-0011) y los Form Requests acotan `account_id`/`category_id`
     * al hogar **activo**, mientras que la autorización miraba el hogar **del
     * recurso**. Con un solo requisito, un miembro de A y B podía editar un
     * recurso de B enlazándole una cuenta de A: el gasto resultante quedaba en
     * B apuntando a una cuenta de A, alteraba el saldo de A y exponía el
     * nombre de esa cuenta a miembros de B ajenos a A.
     *
     * Al exigir que el hogar del recurso sea el activo, hogar-de-validación y
     * hogar-de-autorización son siempre el mismo, y la discrepancia deja de
     * existir. Para operar sobre otro hogar hay que cambiar el hogar activo,
     * que es justo lo que hace la UI.
     */
    protected function canAccessHousehold(User $user, ?int $householdId): bool
    {
        if ($householdId === null || $householdId !== active_household_id()) {
            return false;
        }

        return $this->isMemberOf($user, $householdId);
    }

    /**
     * ¿Hay hogar activo y el usuario es miembro? Para acciones sin instancia
     * (`viewAny`, `create`), donde el hogar solo puede ser el activo.
     */
    protected function canAccessActiveHousehold(User $user): bool
    {
        $household = active_household();

        return $household !== null && $household->hasMember($user);
    }

    /**
     * Membresía pura, sin tener en cuenta el hogar activo.
     */
    private function isMemberOf(User $user, int $householdId): bool
    {
        return $user->households()->where('households.id', $householdId)->exists();
    }
}
