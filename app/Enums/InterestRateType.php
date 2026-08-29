<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Tipo de tasa de interés (Épica 6).
 */
enum InterestRateType: string
{
    case Fixed = 'fixed';
    case Variable = 'variable';

    public function label(): string
    {
        return match ($this) {
            self::Fixed => 'Fija',
            self::Variable => 'Variable',
        };
    }
}
