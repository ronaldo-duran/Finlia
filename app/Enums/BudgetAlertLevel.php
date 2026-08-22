<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Nivel de alerta de un presupuesto según el porcentaje consumido.
 * Umbrales pedidos por la Épica 4: aviso al 80 %, excedido al 100 %.
 */
enum BudgetAlertLevel: string
{
    case Ok = 'ok';
    case Warning = 'warning';
    case Exceeded = 'exceeded';

    public const WARNING_THRESHOLD = 80.0;

    public const EXCEEDED_THRESHOLD = 100.0;

    /**
     * Resuelve el nivel a partir del porcentaje consumido (0-∞).
     */
    public static function fromPercent(float $percent): self
    {
        return match (true) {
            $percent >= self::EXCEEDED_THRESHOLD => self::Exceeded,
            $percent >= self::WARNING_THRESHOLD => self::Warning,
            default => self::Ok,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Ok => 'En rango',
            self::Warning => 'Cerca del límite',
            self::Exceeded => 'Excedido',
        };
    }

    /**
     * Color contextual de Bootstrap 5 (barras, badges, alertas).
     */
    public function color(): string
    {
        return match ($this) {
            self::Ok => 'success',
            self::Warning => 'warning',
            self::Exceeded => 'danger',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Ok => 'bi-check-circle',
            self::Warning => 'bi-exclamation-triangle',
            self::Exceeded => 'bi-x-octagon',
        };
    }
}
