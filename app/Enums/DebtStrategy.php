<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Estrategias de amortización de deuda (Épica 6).
 *
 * La épica pide **preparar la arquitectura**, no resolver el plan de pagos.
 * Aquí solo se define el criterio de ORDEN en que conviene atacar las
 * deudas; el reparto del excedente entre cuotas queda para más adelante.
 */
enum DebtStrategy: string
{
    /** Primero la de mayor tasa: minimiza los intereses totales. */
    case Avalanche = 'avalanche';

    /** Primero la de menor saldo: da victorias tempranas y motiva. */
    case Snowball = 'snowball';

    public function label(): string
    {
        return match ($this) {
            self::Avalanche => 'Avalancha',
            self::Snowball => 'Bola de nieve',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Avalanche => 'Ataca primero la deuda con la tasa más alta. Pagas menos intereses en total.',
            self::Snowball => 'Ataca primero la deuda con el saldo más pequeño. Tachas deudas antes y cuesta menos sostener el hábito.',
        };
    }
}
