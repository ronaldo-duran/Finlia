<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Ventana temporal que el usuario consulta en la pantalla de presupuestos:
 * "esta semana", "este mes" o "próximo mes".
 *
 * No es la periodicidad del presupuesto (eso es BudgetPeriod): los presupuestos
 * se guardan siempre como mensuales y la vista semanal los prorratea.
 */
enum BudgetScope: string
{
    case Week = 'semana';
    case Month = 'mes';
    case NextMonth = 'proximo-mes';

    public function label(): string
    {
        return match ($this) {
            self::Week => 'Esta semana',
            self::Month => 'Este mes',
            self::NextMonth => 'Próximo mes',
        };
    }

    /**
     * Texto para "días restantes de …".
     */
    public function unitLabel(): string
    {
        return match ($this) {
            self::Week => 'de la semana',
            self::Month => 'del mes',
            self::NextMonth => 'del próximo mes',
        };
    }

    /**
     * Mapa valor => etiqueta para selectores y validación.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
