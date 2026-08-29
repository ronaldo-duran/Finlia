<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Estado de una meta de ahorro (Épica 7).
 */
enum SavingsGoalStatus: string
{
    case Active = 'active';
    case Paused = 'paused';
    case Completed = 'completed';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Activa',
            self::Paused => 'Pausada',
            self::Completed => 'Lograda',
            self::Archived => 'Archivada',
        };
    }

    /**
     * Clase de color Bootstrap para el badge de estado.
     */
    public function badgeClass(): string
    {
        return match ($this) {
            self::Active => 'text-bg-primary',
            self::Paused => 'text-bg-warning',
            self::Completed => 'text-bg-success',
            self::Archived => 'text-bg-secondary',
        };
    }

    /**
     * ¿Sigue pidiendo dinero al presupuesto del hogar?
     *
     * Pausada NO: pausar es exactamente dejar de comprometer el aporte
     * mensual. Lograda y archivada tampoco. Es el filtro del término
     * `savings` del dinero disponible (ADR-0014).
     */
    public function countsForSeam(): bool
    {
        return $this === self::Active;
    }

    /**
     * ¿Acepta aportes y retiros? Una meta lograda o archivada es historia:
     * su dinero ya se usó o se guardó; los movimientos sobre ella
     * distorsionarían el historial.
     */
    public function acceptsContributions(): bool
    {
        return $this === self::Active || $this === self::Paused;
    }

    /**
     * Estados que aún se gestionan (panel principal).
     *
     * @return array<int, string>
     */
    public static function outstandingValues(): array
    {
        return [self::Active->value, self::Paused->value];
    }
}
