<?php

namespace Tests\Unit;

use App\Services\DebtCalculator;
use PHPUnit\Framework\TestCase;

/**
 * Matemática del simulador de deuda (ADR-0023). Clase pura: sin base de datos.
 */
class DebtCalculatorTest extends TestCase
{
    private DebtCalculator $calc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calc = new DebtCalculator;
    }

    public function test_la_tasa_anual_se_convierte_a_mensual_equivalente(): void
    {
        // (1 + 0,285)^(1/12) − 1 = 2,1116 %, no 28,5/12 = 2,375 %.
        $this->assertEqualsWithDelta(0.021116, $this->calc->monthlyRate(28.5), 0.000001);
        $this->assertSame(0.0, $this->calc->monthlyRate(0));
        $this->assertSame(0.0, $this->calc->monthlyRate(null));
    }

    public function test_sin_intereses_la_cuota_es_el_reparto_del_monto(): void
    {
        // El caso reportado: 10.000.000 al 0 % en 120 cuotas.
        $this->assertSame(83333.34, $this->calc->installment(10000000, 0, 120));
    }

    public function test_con_intereses_aplica_el_sistema_frances(): void
    {
        $this->assertEqualsWithDelta(479276.56, $this->calc->installment(12000000, 28.5, 36), 0.01);
    }

    /**
     * Si la cuota calculada no salda la deuda en el plazo pactado, el
     * simulador y la proyección se contradicen en pantalla.
     */
    public function test_pagar_la_cuota_calculada_salda_la_deuda_en_el_plazo_exacto(): void
    {
        foreach ([[12000000, 28.5, 36], [10000000, 0, 120], [9000000, 16, 48], [300000000, 11, 240]] as [$monto, $tasa, $cuotas]) {
            $cuota = $this->calc->installment($monto, $tasa, $cuotas);

            $this->assertSame(
                $cuotas,
                $this->calc->payOff($monto, $tasa, $cuota)['months'],
                "Descuadre con $monto al $tasa% en $cuotas cuotas.",
            );
        }
    }

    public function test_datos_incompletos_no_dan_cuota(): void
    {
        $this->assertNull($this->calc->installment(null, 10, 12));
        $this->assertNull($this->calc->installment(1000000, 10, null));
        $this->assertNull($this->calc->installment(0, 10, 12));
        $this->assertNull($this->calc->installment(1000000, 10, 0));
    }

    public function test_una_cuota_que_no_cubre_intereses_no_termina_nunca(): void
    {
        // 24 % E.A. sobre 1.000.000 son ~18.100 al mes de interés.
        $r = $this->calc->payOff(1000000, 24, 15000);

        $this->assertTrue($r['never_ends']);
        $this->assertNull($r['months']);
    }

    public function test_sin_cuota_no_hay_nada_que_proyectar(): void
    {
        $this->assertTrue($this->calc->payOff(1000000, 10, 0)['never_ends']);
        $this->assertTrue($this->calc->payOff(1000000, 10, null)['never_ends']);
    }

    public function test_una_deuda_saldada_no_proyecta(): void
    {
        $r = $this->calc->payOff(0, 10, 50000);

        $this->assertNull($r['months']);
        $this->assertFalse($r['never_ends']);
    }

    public function test_el_caso_reportado_500_meses_pagando_20000(): void
    {
        $this->assertSame(500, $this->calc->payOff(10000000, 0, 20000)['months']);
    }

    public function test_pagar_de_mas_acorta_la_deuda_y_ahorra_intereses(): void
    {
        $cuota = $this->calc->installment(12000000, 28.5, 36);
        $normal = $this->calc->payOff(12000000, 28.5, $cuota);
        $rapido = $this->calc->payOff(12000000, 28.5, $cuota * 2);

        $this->assertLessThan($normal['months'], $rapido['months']);
        $this->assertLessThan($normal['total_interest'], $rapido['total_interest']);
    }
}
