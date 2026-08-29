<?php

declare(strict_types=1);

namespace App\Enums;

use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Períodos comparables de los reportes (Épica 8): mes actual, mes
 * anterior, últimos 3/6 meses o el año.
 *
 * Cada caso resuelve su rango [from, to] anclado a una fecha de referencia
 * y el rango ANTERIOR equivalente (contra el que se compara): el mes
 * anterior se compara con el anterior a aquel, los últimos 3 meses con los
 * 3 previos, y el año con el mismo tramo del año pasado (a la fecha, no el
 * año completo: comparar contra 12 meses cuando solo han pasado 8 sería
 * medir contra algo que no existe todavía).
 */
enum ReportPeriod: string
{
    case Month = 'month';
    case LastMonth = 'last_month';
    case Last3Months = 'last_3_months';
    case Last6Months = 'last_6_months';
    case Year = 'year';

    /** Etiqueta corta para el chip/título del reporte. */
    public function label(): string
    {
        return match ($this) {
            self::Month => 'Mes actual',
            self::LastMonth => 'Mes anterior',
            self::Last3Months => 'Últimos 3 meses',
            self::Last6Months => 'Últimos 6 meses',
            self::Year => 'Año',
        };
    }

    /**
     * Resuelve la ventana del período y su equivalente anterior.
     *
     * @return array{from: Carbon, to: Carbon, previous_from: Carbon, previous_to: Carbon}
     */
    public function resolve(CarbonInterface $reference): array
    {
        $today = Carbon::parse($reference)->startOfDay();

        return match ($this) {
            self::Month => $this->monthWindow($today->copy()->startOfMonth()),
            self::LastMonth => $this->monthWindow($today->copy()->startOfMonth()->subMonth()),
            self::Last3Months => $this->rollingWindow($today, 3),
            self::Last6Months => $this->rollingWindow($today, 6),
            // Año en curso a la fecha, contra el mismo tramo del año pasado.
            self::Year => [
                'from' => $today->copy()->startOfYear(),
                'to' => $today->copy()->endOfDay(),
                'previous_from' => $today->copy()->subYear()->startOfYear(),
                'previous_to' => $today->copy()->subYear()->endOfDay(),
            ],
        };
    }

    /**
     * Un mes natural y el mes anterior a él.
     *
     * @return array{from: Carbon, to: Carbon, previous_from: Carbon, previous_to: Carbon}
     */
    private function monthWindow(Carbon $monthStart): array
    {
        return [
            'from' => $monthStart->copy(),
            'to' => $monthStart->copy()->endOfMonth()->endOfDay(),
            'previous_from' => $monthStart->copy()->subMonth()->startOfMonth(),
            'previous_to' => $monthStart->copy()->subMonth()->endOfMonth()->endOfDay(),
        ];
    }

    /**
     * Ventana móvil de N meses (incluido el actual) y los N meses previos.
     *
     * @return array{from: Carbon, to: Carbon, previous_from: Carbon, previous_to: Carbon}
     */
    private function rollingWindow(Carbon $today, int $months): array
    {
        $from = $today->copy()->startOfMonth()->subMonthsNoOverflow($months - 1);

        return [
            'from' => $from,
            'to' => $today->copy()->endOfDay(),
            'previous_from' => $from->copy()->subMonthsNoOverflow($months)->startOfMonth(),
            'previous_to' => $from->copy()->subDay()->endOfDay(),
        ];
    }
}
