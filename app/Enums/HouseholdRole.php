<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Rol de un usuario dentro de un hogar.
 */
enum HouseholdRole: string
{
    case Owner = 'owner';
    case Member = 'member';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Administrador',
            self::Member => 'Miembro',
        };
    }

    public function isOwner(): bool
    {
        return $this === self::Owner;
    }
}
