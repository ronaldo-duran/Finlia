<?php

namespace Tests\Unit;

use App\Enums\Frequency;
use App\Models\Account;
use App\Models\Household;
use App\Models\RecurringExpense;
use App\Models\User;
use App\Services\HouseholdService;
use App\Services\RecurringExpenseService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Lógica de recurrentes de la Épica 5: fechas (bisiestos incluidos), ahorro
 * mensual necesario, ocurrencias por ventana y "marcar pagado".
 * Todas las fechas son explícitas: nada depende del día real.
 */
class RecurringExpenseServiceTest extends TestCase
{
    use RefreshDatabase;

    private RecurringExpenseService $service;

    private Household $household;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->household = app(HouseholdService::class)->createHousehold($this->owner->id, 'Hogar A');
        $this->service = app(RecurringExpenseService::class);
    }

    private function recurring(array $attributes = []): RecurringExpense
    {
        return $this->household->recurringExpenses()->create([
            'name' => 'SOAT',
            'amount' => 600000,
            'frequency' => Frequency::Yearly->value,
            'next_date' => '2026-11-15',
            'is_active' => true,
            ...$attributes,
        ]);
    }

    // ===== Ahorro mensual necesario =====

    public function test_soat_anual_de_600_mil_exige_50_mil_mensuales(): void
    {
        $this->assertSame(50000.0, $this->service->monthlySavings($this->recurring()));
    }

    public function test_ahorro_mensual_por_frecuencia(): void
    {
        $cases = [
            [Frequency::Weekly, 30000, 130000.0],      // 30.000 × 52 / 12
            [Frequency::Biweekly, 200000, 400000.0],   // 200.000 × 24 / 12
            [Frequency::Monthly, 300000, 300000.0],    // identidad
            [Frequency::Quarterly, 900000, 300000.0],
            [Frequency::Semester, 600000, 100000.0],
            [Frequency::Custom, 200000, 101388.89],    // 200.000 × (365/60) / 12
        ];

        foreach ($cases as [$frequency, $amount, $expected]) {
            $recurring = $this->recurring([
                'frequency' => $frequency->value,
                'frequency_interval' => $frequency === Frequency::Custom ? 60 : null,
                'amount' => $amount,
            ]);

            $this->assertSame($expected, $this->service->monthlySavings($recurring), (string) $frequency->value);
        }
    }

    public function test_total_mensual_solo_suma_activos(): void
    {
        $this->recurring(['name' => 'SOAT']); // 50.000/mes
        $this->recurring(['name' => 'Pausado', 'is_active' => false]);

        $this->assertSame(50000.0, $this->service->totalMonthlySavings($this->household->id));
    }

    // ===== Fechas: avance y años bisiestos =====

    public function test_mensual_desde_31_de_enero_cae_en_28_de_febrero(): void
    {
        $next = Frequency::Monthly->advance(Carbon::parse('2026-01-31'));

        $this->assertSame('2026-02-28', $next->toDateString());
    }

    public function test_anual_desde_29_de_febrero_bisiesto_cae_en_28(): void
    {
        // 2028 es bisiesto; +1 año sin desbordar no inventa un 29/feb/2029.
        $next = Frequency::Yearly->advance(Carbon::parse('2028-02-29'));

        $this->assertSame('2029-02-28', $next->toDateString());
    }

    public function test_anual_hacia_anio_bisiesto_no_inventa_29(): void
    {
        $next = Frequency::Yearly->advance(Carbon::parse('2027-02-28'));

        $this->assertSame('2028-02-28', $next->toDateString());
    }

    public function test_quincenal_avanza_15_dias(): void
    {
        $next = Frequency::Biweekly->advance(Carbon::parse('2026-08-01'));

        $this->assertSame('2026-08-16', $next->toDateString());
    }

    public function test_trimestral_y_semestral_sin_desborde(): void
    {
        $this->assertSame('2026-06-30', Frequency::Quarterly->advance(Carbon::parse('2026-03-31'))->toDateString());
        $this->assertSame('2026-08-28', Frequency::Semester->advance(Carbon::parse('2026-02-28'))->toDateString());
    }

    // ===== Próximas obligaciones =====

    public function test_upcoming_ordena_por_fecha_y_marca_vencidas(): void
    {
        $this->recurring(['name' => 'Futura', 'next_date' => '2026-09-10']);
        $this->recurring(['name' => 'Vencida', 'frequency' => Frequency::Monthly->value, 'next_date' => '2026-08-20']);

        $upcoming = $this->service->upcoming($this->household->id, Carbon::parse('2026-08-27'));

        $this->assertSame('Vencida', $upcoming->first()['name']);
        $this->assertTrue($upcoming->first()['is_overdue']);
        $this->assertSame(-7, $upcoming->first()['days_remaining']);
        $this->assertSame(14, $upcoming->last()['days_remaining']);
        $this->assertFalse($upcoming->last()['is_overdue']);
    }

    public function test_upcoming_solo_incluye_activos(): void
    {
        $this->recurring(['name' => 'Pausado', 'is_active' => false]);

        $this->assertCount(0, $this->service->upcoming($this->household->id, Carbon::parse('2026-08-27')));
    }

    public function test_alerts_solo_incluyen_ventana_y_vencidas(): void
    {
        $this->recurring(['name' => 'Lejos', 'next_date' => '2026-12-15']);      // > 30 días
        $this->recurring(['name' => 'Cerca', 'next_date' => '2026-09-20']);      // 24 días
        $this->recurring(['name' => 'Vencida', 'frequency' => Frequency::Monthly->value, 'next_date' => '2026-08-25']);

        $alerts = $this->service->alerts($this->household->id, Carbon::parse('2026-08-27'));

        $this->assertSame(['Vencida', 'Cerca'], $alerts->pluck('name')->all());
    }

    // ===== Comprometido por ventana (seams del calculador) =====

    public function test_arriendo_mensual_cuenta_una_vez_y_va_en_fijos(): void
    {
        $this->recurring([
            'name' => 'Arriendo', 'amount' => 1200000,
            'frequency' => Frequency::Monthly->value, 'next_date' => '2026-08-05',
        ]);

        $committed = $this->service->committedInRange(
            $this->household->id,
            Carbon::parse('2026-08-01'),
            Carbon::parse('2026-08-31'),
        );

        $this->assertSame(1200000.0, $committed['fixed']);
        $this->assertSame(0.0, $committed['recurring']);
        $this->assertSame(1200000.0, $committed['total']);
    }

    public function test_obligacion_anual_en_la_ventana_va_en_recurring(): void
    {
        $this->recurring(['next_date' => '2026-08-20']); // SOAT anual

        $committed = $this->service->committedInRange(
            $this->household->id,
            Carbon::parse('2026-08-01'),
            Carbon::parse('2026-08-31'),
        );

        $this->assertSame(0.0, $committed['fixed']);
        $this->assertSame(600000.0, $committed['recurring']);
    }

    public function test_obligacion_fuera_de_la_ventana_no_cuenta(): void
    {
        $this->recurring(['next_date' => '2026-12-15']);

        $committed = $this->service->committedInRange(
            $this->household->id,
            Carbon::parse('2026-08-01'),
            Carbon::parse('2026-08-31'),
        );

        $this->assertSame(0.0, $committed['total']);
    }

    public function test_semanal_cuenta_todas_sus_ocurrencias_del_mes(): void
    {
        // 3, 10, 17, 24 y 31 de agosto: cinco ocurrencias.
        $this->recurring([
            'name' => 'Mercado', 'amount' => 50000,
            'frequency' => Frequency::Weekly->value, 'next_date' => '2026-08-03',
        ]);

        $committed = $this->service->committedInRange(
            $this->household->id,
            Carbon::parse('2026-08-01'),
            Carbon::parse('2026-08-31'),
        );

        $this->assertSame(250000.0, $committed['fixed']);
    }

    public function test_vencida_cuenta_su_siguiente_ocurrencia_real(): void
    {
        // Venció el 20/07 sin marcar pagado: en agosto cuenta la del día 20.
        $this->recurring([
            'name' => 'Arriendo', 'amount' => 1200000,
            'frequency' => Frequency::Monthly->value, 'next_date' => '2026-07-20',
        ]);

        $committed = $this->service->committedInRange(
            $this->household->id,
            Carbon::parse('2026-08-01'),
            Carbon::parse('2026-08-31'),
        );

        $this->assertSame(1200000.0, $committed['fixed']);
    }

    public function test_personalizada_mayor_a_31_dias_es_obligacion_no_fijo(): void
    {
        $this->recurring([
            'frequency' => Frequency::Custom->value,
            'frequency_interval' => 45,
            'next_date' => '2026-08-10',
        ]);

        $committed = $this->service->committedInRange(
            $this->household->id,
            Carbon::parse('2026-08-01'),
            Carbon::parse('2026-08-31'),
        );

        $this->assertSame(0.0, $committed['fixed']);
        $this->assertSame(600000.0, $committed['recurring']);
    }

    public function test_comprometrido_no_mezcla_hogares(): void
    {
        $intruso = User::factory()->create();
        $otro = app(HouseholdService::class)->createHousehold($intruso->id, 'Hogar B');
        $otro->recurringExpenses()->create([
            'name' => 'Arriendo ajeno', 'amount' => 9000000,
            'frequency' => Frequency::Monthly->value, 'next_date' => '2026-08-05',
        ]);

        $committed = $this->service->committedInRange(
            $this->household->id,
            Carbon::parse('2026-08-01'),
            Carbon::parse('2026-08-31'),
        );

        $this->assertSame(0.0, $committed['total']);
    }

    // ===== Marcar pagado =====

    public function test_marcar_pagado_registra_el_gasto_y_avanza_la_fecha(): void
    {
        $account = Account::factory()->create([
            'household_id' => $this->household->id,
            'initial_balance' => 1000000,
            'current_balance' => 1000000,
        ]);
        $recurring = $this->recurring(['account_id' => $account->id]);

        $expense = $this->service->markAsPaid($recurring, $this->owner, Carbon::parse('2026-08-27'));

        $this->assertNotNull($expense);
        $this->assertSame(600000.0, (float) $expense->amount);
        $this->assertSame('2026-08-27', $expense->date->toDateString());
        $this->assertSame('SOAT (pago recurrente)', $expense->description);
        // Saldo recomputado dentro de la transacción (ADR-0012).
        $this->assertSame(400000.0, (float) $account->fresh()->current_balance);
        // Anual: 15/nov/2026 → 15/nov/2027.
        $this->assertSame('2027-11-15', $recurring->fresh()->next_date->toDateString());
    }

    public function test_marcar_pagado_sin_cuenta_solo_avanza_la_fecha(): void
    {
        $recurring = $this->recurring();

        $expense = $this->service->markAsPaid($recurring, $this->owner, Carbon::parse('2026-08-27'));

        $this->assertNull($expense);
        $this->assertSame(0, $recurring->household->expenses()->count());
        $this->assertSame('2027-11-15', $recurring->fresh()->next_date->toDateString());
    }

    public function test_al_marcar_pagado_la_ocurrencia_sale_del_comprometido(): void
    {
        $account = Account::factory()->create(['household_id' => $this->household->id]);
        $recurring = $this->recurring([
            'name' => 'Arriendo', 'amount' => 1200000,
            'frequency' => Frequency::Monthly->value, 'next_date' => '2026-08-20',
            'account_id' => $account->id,
        ]);

        [$from, $to] = [Carbon::parse('2026-08-01'), Carbon::parse('2026-08-31')];

        $this->assertSame(1200000.0, $this->service->committedInRange($this->household->id, $from, $to)['total']);

        $this->service->markAsPaid($recurring, $this->owner, Carbon::parse('2026-08-27'));

        // El gasto quedó registrado (una sola vez) y el comprometido bajó a cero.
        $this->assertSame(1, $this->household->expenses()->count());
        $this->assertSame(0.0, $this->service->committedInRange($this->household->id, $from, $to)['total']);
    }
}
