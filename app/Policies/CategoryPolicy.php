<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Category;
use App\Models\User;

/**
 * Autorización sobre categorías.
 * - Las globales (household_id NULL, del seed) son legibles por todos, pero
 *   no editables ni borrables (gestionadas por el sistema).
 * - Las personales se gestionan solo por miembros del hogar dueño.
 */
class CategoryPolicy
{
    public function view(User $user, Category $category): bool
    {
        // Globales: visibles para cualquier usuario autenticado.
        if ($category->household_id === null) {
            return true;
        }

        return $this->userInHousehold($user, $category->household_id);
    }

    public function create(User $user): bool
    {
        $household = active_household();

        return $household !== null && $household->hasMember($user);
    }

    public function update(User $user, Category $category): bool
    {
        return $this->isOwnedByUserHousehold($user, $category);
    }

    public function delete(User $user, Category $category): bool
    {
        // Las globales o marcadas por defecto no se borran por UI.
        if ($category->household_id === null || $category->is_default) {
            return false;
        }

        return $this->isOwnedByUserHousehold($user, $category);
    }

    private function isOwnedByUserHousehold(User $user, Category $category): bool
    {
        return $category->household_id !== null
            && $this->userInHousehold($user, $category->household_id);
    }

    private function userInHousehold(User $user, int $householdId): bool
    {
        return $user->households()->where('households.id', $householdId)->exists();
    }
}
