<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Category;
use App\Models\User;
use App\Policies\Concerns\ChecksHouseholdAccess;

/**
 * Autorización sobre categorías.
 * - Las globales (household_id NULL, del seed) son legibles por todos, pero
 *   no editables ni borrables (gestionadas por el sistema).
 * - Las personales requieren ser miembro del hogar dueño Y que ese hogar sea
 *   el activo (ver ChecksHouseholdAccess).
 */
class CategoryPolicy
{
    use ChecksHouseholdAccess;

    public function view(User $user, Category $category): bool
    {
        // Globales: visibles para cualquier usuario autenticado.
        if ($category->household_id === null) {
            return true;
        }

        return $this->canAccessHousehold($user, $category->household_id);
    }

    public function create(User $user): bool
    {
        return $this->canAccessActiveHousehold($user);
    }

    /**
     * canAccessHousehold rechaza household_id NULL, así que las globales
     * quedan fuera sin necesidad de una comprobación extra.
     */
    public function update(User $user, Category $category): bool
    {
        return $this->canAccessHousehold($user, $category->household_id);
    }

    public function delete(User $user, Category $category): bool
    {
        // Las marcadas por defecto no se borran por UI.
        if ($category->is_default) {
            return false;
        }

        return $this->canAccessHousehold($user, $category->household_id);
    }
}
