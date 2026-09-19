<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * "¿Qué lo disparó?" — paso 4 del árbol.
 */
enum CompulsiveTrigger: string
{
    case Stress = 'stress';
    case Joy = 'joy';
    case Boredom = 'boredom';
    case RealNeed = 'real_need';
    case SocialPressure = 'social_pressure';
    case Promotion = 'promotion';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Stress => 'Estrés',
            self::Joy => 'Alegría',
            self::Boredom => 'Aburrimiento',
            self::RealNeed => 'Necesidad real',
            self::SocialPressure => 'Presión social',
            self::Promotion => 'Promoción o descuento',
            self::Other => 'Otro',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
