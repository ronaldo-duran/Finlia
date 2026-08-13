<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Estado de una invitación a un hogar.
 */
enum InvitationStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Expired = 'expired';
    case Revoked = 'revoked';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente',
            self::Accepted => 'Aceptada',
            self::Expired => 'Expirada',
            self::Revoked => 'Revocada',
        };
    }
}
