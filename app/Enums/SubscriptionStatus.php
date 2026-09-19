<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Estado de una suscripción de hogar (Épica 12).
 */
enum SubscriptionStatus: string
{
    case Active = 'active';
    case Trialing = 'trialing';
    case Canceled = 'canceled';
    case PastDue = 'past_due';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Activa',
            self::Trialing => 'En prueba',
            self::Canceled => 'Cancelada',
            self::PastDue => 'Con pago pendiente',
        };
    }

    /**
     * Estados en los que la suscripción "vale": el hogar disfruta las features
     * y límites de su plan. Los demás caen al plan por defecto (Free).
     */
    public function grantsPlan(): bool
    {
        return match ($this) {
            self::Active, self::Trialing => true,
            self::Canceled, self::PastDue => false,
        };
    }
}
