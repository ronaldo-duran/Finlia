<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Periodicidad de un presupuesto.
 *
 * La Épica 4 solo soporta presupuestos MENSUALES: es la unidad natural del
 * producto (sueldo, arriendo, servicios) y la que usa el cálculo de dinero
 * disponible. Las consultas semanales se resuelven prorrateando el mensual
 * (ver BudgetScope), no con presupuestos semanales.
 *
 * `weekly` / `yearly` quedan reservados para una épica futura; añadirlos aquí
 * exige extender BudgetCalculatorService, así que no se declaran todavía.
 */
enum BudgetPeriod: string
{
    case Monthly = 'monthly';

    public function label(): string
    {
        return match ($this) {
            self::Monthly => 'Mensual',
        };
    }
}
