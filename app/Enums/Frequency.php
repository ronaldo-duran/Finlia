<?php

declare(strict_types=1);

namespace App\Enums;

use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Frecuencia de un gasto recurrente (Épica 5).
 *
 * La matemática de fechas vive aquí (advance) para que el Service y una
 * futura API (ADR-0010) compartan exactamente el mismo criterio, incluido
 * el comportamiento ante fin de mes y años bisiestos.
 */
enum Frequency: string
{
    case Weekly = 'weekly';
    case Biweekly = 'biweekly';
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case Semester = 'semester';
    case Yearly = 'yearly';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::Weekly => 'Semanal',
            self::Biweekly => 'Quincenal',
            self::Monthly => 'Mensual',
            self::Quarterly => 'Trimestral',
            self::Semester => 'Semestral',
            self::Yearly => 'Anual',
            self::Custom => 'Personalizada',
        };
    }

    /**
     * Etiqueta corta para listados compactos ("cada 15 días").
     */
    public function shortLabel(?int $interval = null): string
    {
        return match ($this) {
            self::Custom => 'cada '.max(1, (int) ($interval ?? 0)).' días',
            default => mb_strtolower($this->label()),
        };
    }

    /**
     * Siguiente ocurrencia a partir de una fecha. Sin desbordamiento:
     * 31/ene + 1 mes = 28/feb, y 29/feb/2028 + 1 año = 28/feb/2029.
     */
    public function advance(CarbonInterface $date, ?int $interval = null): Carbon
    {
        $next = Carbon::parse($date)->startOfDay();

        return match ($this) {
            self::Weekly => $next->addDays(7),
            self::Biweekly => $next->addDays(15), // quincena = 15 días
            self::Monthly => $next->addMonthNoOverflow(),
            self::Quarterly => $next->addMonthsNoOverflow(3),
            self::Semester => $next->addMonthsNoOverflow(6),
            self::Yearly => $next->addYearNoOverflow(),
            self::Custom => $next->addDays(max(1, (int) ($interval ?? 30))),
        };
    }

    /**
     * Ocurrencias por año, base del ahorro mensual recomendado:
     * SOAT de $600.000 anual → 600.000 × 1 / 12 = $50.000 mensuales.
     */
    public function occurrencesPerYear(?int $interval = null): float
    {
        return match ($this) {
            self::Weekly => 52.0,
            self::Biweekly => 24.0, // ≈ dos quincenas por mes
            self::Monthly => 12.0,
            self::Quarterly => 4.0,
            self::Semester => 2.0,
            self::Yearly => 1.0,
            self::Custom => 365.0 / max(1, (int) ($interval ?? 30)),
        };
    }

    /**
     * True si la frecuencia se comporta como "gasto fijo" (algo que se paga
     * cada mes o más seguido: arriendo, internet, servicios). Los de baja
     * frecuencia (trimestral en adelante) son las "obligaciones futuras".
     *
     * Determina qué seam del calculador rellena cada recurrente:
     * fixed_expenses vs recurring (ADR-0014).
     */
    public function isFixedLike(?int $interval = null): bool
    {
        return match ($this) {
            self::Weekly, self::Biweekly, self::Monthly => true,
            self::Quarterly, self::Semester, self::Yearly => false,
            self::Custom => $interval !== null && $interval <= 31,
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
