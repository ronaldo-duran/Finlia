<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\McpToken;
use App\Models\User;

/**
 * Autorización sobre tokens MCP: cada quien gestiona los suyos y nadie más.
 * No depende del hogar activo porque un token puede estar atado a un hogar
 * al que el usuario ya no pertenezca, y aun así debe poder revocarlo.
 */
class McpTokenPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->households()->exists();
    }

    public function update(User $user, McpToken $token): bool
    {
        return $token->user_id === $user->id;
    }

    public function delete(User $user, McpToken $token): bool
    {
        return $token->user_id === $user->id;
    }
}
