<?php

namespace Tests\Unit;

use App\Enums\DebtPaymentType;
use App\Enums\DebtStatus;
use App\Enums\DebtStrategy;
use App\Models\Debt;
use App\Models\Household;
use App\Models\User;
use App\Services\DebtService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cálculos de DebtService (Épica 6): saldo derivado, proyección de fin de
 * deuda, estrategias y el término que alimenta el dinero disponible.
 */
class DebtServiceTest extends TestCase
{
    use RefreshDatabase;

    private DebtService $service;

    private Household $household;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(DebtService::class);
        $this->user = User::factory()->create();
        $this->household = Household::factory()->create(['owner_id' => $this->user->id]);
    }

    private function debt(array $attributes = []): Debt
    {
        return Debt::factory()->create([
            'household_id' => $this->household->id,
            ...$attributes,
        ]);
    }

    // ---- Saldo derivado (ADR-0020) ----

    public function test_el_saldo_es_el_monto_original_menos_los_pagos(): void
    {
        // El ejemplo exacto de la épica: 4.800.000 − 800.000 = 4.000.000.
        $debt = $this->debt(['original_amount' => 4800000, 'current_balance' => 4800000]);

        $this->service->registerPayment($debt, [
            'amount' => 800000,
            'date' => now()->toDateString(),
            'type' => DebtPaymentType::Scheduled->value,
        ], $this->user);

        $this->assertSame('4000000.00', $debt->fresh()->current_balance);
    }

    public function test_varios_pagos_se_acumulan(): void
    {
        $debt = $this->debt(['original_amount' => 1000000, 'current_balance' => 1000000]);

        foreach ([200000, 150000, 50000] as $amount) {
            $this->service->registerPayment($debt, [
                'amount' => $amount,
                'date' => now()->toDateString(),
                'type' => DebtPaymentType::Extra->value,
            ], $this->user);
        }

        $this->assertSame('600000.00', $debt->fresh()->current_balance);
    }

    public function test_el_saldo_nunca_baja_de_cero_y_la_deuda_queda_pagada(): void
    {
        $debt = $this->debt(['original_amount' => 100000, 'current_balance' => 100000]);

        // Sobrepago: 150.000 sobre una deuda de 100.000.
        $this->service->registerPayment($debt, [
            'amount' => 150000,
            'date' => now()->toDateString(),
            'type' => DebtPaymentType::Extra->value,
        ], $this->user);

        $debt->refresh();
        $this->assertSame('0.00', $debt->current_balance);
        $this->assertSame(DebtStatus::Paid, $debt->status);
    }

    public function test_borrar_un_pago_devuelve_el_saldo_y_reactiva_la_deuda(): void
    {
        $debt = $this->debt(['original_amount' => 500000, 'current_balance' => 500000]);

        $payment = $this->service->registerPayment($debt, [
            'amount' => 500000,
            'date' => now()->toDateString(),
            'type' => DebtPaymentType::Scheduled->value,
        ], $this->user);

        $this->assertSame(DebtStatus::Paid, $debt->fresh()->status);

        $this->service->deletePayment($payment);

        $debt->refresh();
        $this->assertSame('500000.00', $debt->current_balance);
        $this->assertSame(DebtStatus::Active, $debt->status);
    }

    public function test_la_refinanciacion_fija_una_nueva_linea_base(): void
    {
        $debt = $this->debt(['original_amount' => 1000000, 'current_balance' => 1000000]);

        // Pago anterior a la refinanciación: ya está incorporado en el saldo
        // refinanciado, así que NO debe volver a restarse.
        $this->service->registerPayment($debt, [
            'amount' => 200000,
            'date' => now()->subMonths(2)->toDateString(),
            'type' => DebtPaymentType::Scheduled->value,
        ], $this->user);

        $this->service->registerRefinancing($debt, [
            'refinanced_balance' => 900000,
            'interest_rate' => 18.5,
            'term_months' => 24,
            'installment' => 45000,
            'start_date' => now()->subMonth()->toDateString(),
        ]);

        $debt->refresh();
        $this->assertSame('900000.00', $debt->current_balance);
        $this->assertSame(DebtStatus::Refinanced, $debt->status);
        // Las nuevas condiciones se copian a la deuda.
        $this->assertSame('45000.00', $debt->scheduled_payment);

        // Un pago posterior sí resta del nuevo saldo.
        $this->service->registerPayment($debt, [
            'amount' => 45000,
            'date' => now()->toDateString(),
            'type' => DebtPaymentType::Scheduled->value,
        ], $this->user);

        $this->assertSame('855000.00', $debt->fresh()->current_balance);
    }

    // ---- Proyección ----

    public function test_proyecta_el_fin_de_una_deuda_sin_intereses(): void
    {
        $debt = $this->debt([
            'original_amount' => 1000000,
            'current_balance' => 1000000,
            'interest_rate' => 0,
            'scheduled_payment' => 100000,
        ]);

        $projection = $this->service->projectPayoff($debt, Carbon::parse('2026-01-15'));

        $this->assertSame(10, $projection['months']);
        $this->assertSame(0.0, $projection['total_interest']);
        $this->assertFalse($projection['never_ends']);
        $this->assertSame('2026-11-15', $projection['date']->toDateString());
    }

    public function test_la_proyeccion_con_intereses_tarda_mas_y_los_suma(): void
    {
        $debt = $this->debt([
            'original_amount' => 1000000,
            'current_balance' => 1000000,
            'interest_rate' => 24, // 2 % mensual
            'scheduled_payment' => 100000,
        ]);

        $projection = $this->service->projectPayoff($debt);

        $this->assertGreaterThan(10, $projection['months']);
        $this->assertGreaterThan(0, $projection['total_interest']);
        $this->assertFalse($projection['never_ends']);
    }

    public function test_una_cuota_que_no_cubre_intereses_no_termina_nunca(): void
    {
        // 2 % mensual sobre 1.000.000 son 20.000 de intereses; la cuota es menor.
        $debt = $this->debt([
            'original_amount' => 1000000,
            'current_balance' => 1000000,
            'interest_rate' => 24,
            'scheduled_payment' => 15000,
            'minimum_payment' => null,
        ]);

        $projection = $this->service->projectPayoff($debt);

        $this->assertTrue($projection['never_ends']);
        $this->assertNull($projection['months']);
    }

    public function test_sin_cuota_registrada_no_se_puede_proyectar(): void
    {
        $debt = Debt::factory()->withoutInstallment()->create([
            'household_id' => $this->household->id,
            'current_balance' => 500000,
        ]);

        $projection = $this->service->projectPayoff($debt);

        $this->assertTrue($projection['never_ends']);
        $this->assertNull($projection['date']);
    }

    public function test_una_deuda_saldada_no_proyecta_nada(): void
    {
        $debt = $this->debt(['current_balance' => 0, 'scheduled_payment' => 50000]);

        $projection = $this->service->projectPayoff($debt);

        $this->assertNull($projection['months']);
        $this->assertFalse($projection['never_ends']);
    }

    // ---- Resumen y estrategias ----

    public function test_el_resumen_suma_saldo_y_compromiso_mensual(): void
    {
        $this->debt(['original_amount' => 1000000, 'current_balance' => 600000, 'scheduled_payment' => 50000]);
        $this->debt(['original_amount' => 500000, 'current_balance' => 500000, 'scheduled_payment' => 30000]);
        // Pagada: no cuenta.
        $this->debt(['original_amount' => 200000, 'current_balance' => 0, 'scheduled_payment' => 10000, 'status' => DebtStatus::Paid]);

        $summary = $this->service->summary($this->household->id);

        $this->assertSame(1100000.0, $summary['total_balance']);
        $this->assertSame(80000.0, $summary['monthly_commitment']);
        $this->assertSame(2, $summary['count']);
        $this->assertSame(400000.0, $summary['total_paid']);
    }

    public function test_avalancha_ordena_por_tasa_y_bola_de_nieve_por_saldo(): void
    {
        $cara = $this->debt(['name' => 'Cara', 'interest_rate' => 30, 'current_balance' => 900000]);
        $barata = $this->debt(['name' => 'Barata', 'interest_rate' => 8, 'current_balance' => 100000]);

        $avalancha = $this->service->orderByStrategy($this->household->id, DebtStrategy::Avalanche);
        $this->assertSame($cara->id, $avalancha->first()->id);

        $bola = $this->service->orderByStrategy($this->household->id, DebtStrategy::Snowball);
        $this->assertSame($barata->id, $bola->first()->id);
    }

    public function test_el_resumen_solo_ve_deudas_del_hogar(): void
    {
        $this->debt(['current_balance' => 500000, 'scheduled_payment' => 50000]);

        $otro = Household::factory()->create();
        Debt::factory()->create(['household_id' => $otro->id, 'current_balance' => 9999999]);

        $this->assertSame(500000.0, $this->service->summary($this->household->id)['total_balance']);
    }

    // ---- Seam del dinero disponible (ADR-0014) ----

    public function test_las_cuotas_pendientes_del_periodo_cuentan_como_comprometido(): void
    {
        $this->debt([
            'current_balance' => 1000000,
            'scheduled_payment' => 120000,
            'due_day' => 15,
        ]);

        $committed = $this->service->committedInRange(
            $this->household->id,
            Carbon::parse('2026-03-01'),
            Carbon::parse('2026-03-31'),
        );

        $this->assertSame(120000.0, $committed);
    }

    public function test_una_cuota_ya_pagada_sale_del_comprometido(): void
    {
        $debt = $this->debt([
            'current_balance' => 1000000,
            'scheduled_payment' => 120000,
            'due_day' => 15,
        ]);

        // Pago dentro del mismo mes de la cuota.
        $this->service->registerPayment($debt, [
            'amount' => 120000,
            'date' => '2026-03-10',
            'type' => DebtPaymentType::Scheduled->value,
        ], $this->user);

        $committed = $this->service->committedInRange(
            $this->household->id,
            Carbon::parse('2026-03-01'),
            Carbon::parse('2026-03-31'),
        );

        // Sin esta resta, pagar bajaría el "puedes gastar" dos veces.
        $this->assertSame(0.0, $committed);
    }

    public function test_la_ultima_cuota_no_supera_el_saldo_que_queda(): void
    {
        $this->debt([
            'current_balance' => 30000, // queda menos que la cuota
            'scheduled_payment' => 120000,
            'due_day' => 15,
        ]);

        $committed = $this->service->committedInRange(
            $this->household->id,
            Carbon::parse('2026-03-01'),
            Carbon::parse('2026-03-31'),
        );

        $this->assertSame(30000.0, $committed);
    }

    public function test_una_deuda_sin_cuota_no_compromete_nada(): void
    {
        Debt::factory()->withoutInstallment()->create([
            'household_id' => $this->household->id,
            'current_balance' => 800000,
            'due_day' => 10,
        ]);

        $committed = $this->service->committedInRange(
            $this->household->id,
            Carbon::parse('2026-03-01'),
            Carbon::parse('2026-03-31'),
        );

        $this->assertSame(0.0, $committed);
    }

    public function test_un_dia_31_cae_en_el_ultimo_dia_de_un_mes_corto(): void
    {
        $this->debt([
            'current_balance' => 1000000,
            'scheduled_payment' => 50000,
            'due_day' => 31,
        ]);

        // Febrero de 2026 tiene 28 días: la cuota debe caer el 28, no perderse.
        $committed = $this->service->committedInRange(
            $this->household->id,
            Carbon::parse('2026-02-01'),
            Carbon::parse('2026-02-28'),
        );

        $this->assertSame(50000.0, $committed);
    }

    public function test_una_deuda_pagada_no_compromete_nada(): void
    {
        $this->debt([
            'current_balance' => 0,
            'scheduled_payment' => 90000,
            'due_day' => 5,
            'status' => DebtStatus::Paid,
        ]);

        $committed = $this->service->committedInRange(
            $this->household->id,
            Carbon::parse('2026-03-01'),
            Carbon::parse('2026-03-31'),
        );

        $this->assertSame(0.0, $committed);
    }
}
