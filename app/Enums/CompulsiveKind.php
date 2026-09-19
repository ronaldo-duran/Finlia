<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * "¿Cómo lo llamarías?" — paso 2 del árbol.
 */
enum CompulsiveKind: string
{
    case Need = 'need';
    case Want = 'want';
    case Impulse = 'impulse';
    case Emergency = 'emergency';

    public function label(): string
    {
        return match ($this) {
            self::Need => 'Necesidad',
            self::Want => 'Gusto',
            self::Impulse => 'Capricho',
            self::Emergency => 'Emergencia',
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
