<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Prioridad de una meta de ahorro (Épica 7). Informativa: ordena el panel
 * y el fondo de emergencia suele llevar `high`.
 */
enum SavingsGoalPriority: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';

    public function label(): string
    {
        return match ($this) {
            self::Low => 'Baja',
            self::Medium => 'Media',
            self::High => 'Alta',
        };
    }

    /**
     * Clase de color Bootstrap para el badge.
     */
    public function badgeClass(): string
    {
        return match ($this) {
            self::Low => 'text-bg-secondary',
            self::Medium => 'text-bg-warning',
            self::High => 'text-bg-danger',
        };
    }
}
