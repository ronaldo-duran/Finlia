<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Matemática financiera de una deuda (ADR-0023): cuota, plazo e intereses.
 *
 * Clase pura: no toca base de datos, HTTP ni modelos. Sirve igual para el
 * simulador del formulario, para la validación de coherencia y para la
 * proyección del panel, de modo que las tres cifras no puedan contradecirse.
 *
 * **Convención de tasa.** El campo se pide como **efectiva anual (E.A.)**,
 * que es como se cotiza el crédito en Colombia. La equivalente mensual es
 * `(1 + EA)^(1/12) − 1`, NO `EA / 12`: dividir entre doce es la convención
 * nominal y sobreestima el interés (con 28,5 % E.A. da 2,375 % frente al
 * 2,114 % real).
 */
class DebtCalculator
{
    /** Techo de meses que se simula hacia adelante (50 años). */
    public const MAX_MONTHS = 600;

    /**
     * Tasa mensual equivalente a partir de la efectiva anual en porcentaje.
     */
    public function monthlyRate(?float $annualRatePercent): float
    {
        $annual = ($annualRatePercent ?? 0.0) / 100;

        if ($annual <= 0.0) {
            return 0.0;
        }

        return ($annual + 1) ** (1 / 12) - 1;
    }

    /**
     * Cuota fija que amortiza `$principal` en `$months` (sistema francés).
     *
     * Sin intereses es un simple reparto. Con intereses:
     *     cuota = P · i / (1 − (1 + i)^−n)
     *
     * Devuelve null si los datos no permiten calcularla.
     */
    public function installment(?float $principal, ?float $annualRatePercent, ?int $months): ?float
    {
        if ($principal === null || $principal <= 0.0 || $months === null || $months < 1) {
            return null;
        }

        $i = $this->monthlyRate($annualRatePercent);

        $cuota = $i <= 0.0
            ? $principal / $months
            : $principal * $i / (1 - (1 + $i) ** (-$months));

        // Se redondea al céntimo HACIA ARRIBA, no al más cercano: con el
        // redondeo normal la cuota se queda unos céntimos corta y haría falta
        // un mes extra para saldar el resto, así que el simulador diría "36
        // cuotas" y la proyección "37 meses". Los bancos hacen lo mismo.
        return ceil($cuota * 100) / 100;
    }

    /**
     * Interés del primer mes: el suelo por debajo del cual una cuota no
     * amortiza nada y la deuda no baja nunca.
     */
    public function firstMonthInterest(?float $balance, ?float $annualRatePercent): float
    {
        if ($balance === null || $balance <= 0.0) {
            return 0.0;
        }

        return round($balance * $this->monthlyRate($annualRatePercent), 2);
    }

    /**
     * Cuántos meses se tarda en saldar `$balance` pagando `$payment` al mes,
     * y cuánto interés se paga por el camino.
     *
     * `months` es null cuando no termina nunca: sin cuota, o con una cuota
     * que no cubre ni los intereses.
     *
     * @return array{months: ?int, total_interest: float, never_ends: bool}
     */
    public function payOff(?float $balance, ?float $annualRatePercent, ?float $payment): array
    {
        $vacio = ['months' => null, 'total_interest' => 0.0, 'never_ends' => false];

        if ($balance === null || $balance <= 0.0) {
            return $vacio;
        }

        if ($payment === null || $payment <= 0.0) {
            return [...$vacio, 'never_ends' => true];
        }

        $i = $this->monthlyRate($annualRatePercent);

        // Si la cuota no cubre el interés del primer mes, el saldo crece.
        if ($payment <= $balance * $i) {
            return [...$vacio, 'never_ends' => true];
        }

        $interes = 0.0;
        $meses = 0;

        // Epsilon de medio céntimo: un residuo menor que eso es ruido de
        // redondeo, no una cuota más.
        while ($balance > 0.005 && $meses < self::MAX_MONTHS) {
            $delMes = round($balance * $i, 2);
            $interes += $delMes;
            $balance = round($balance + $delMes - $payment, 2);
            $meses++;
        }

        if ($balance > 0.005) {
            return [...$vacio, 'never_ends' => true];
        }

        return ['months' => $meses, 'total_interest' => round($interes, 2), 'never_ends' => false];
    }
}
