<?php

namespace Tests\Unit;

use App\Enums\AccountType;
use App\Enums\BudgetAlertLevel;
use App\Enums\BudgetScope;
use App\Enums\CategoryType;
use App\Enums\Frequency;
use App\Enums\SavingsGoalContributionType;
use App\Enums\SavingsGoalStatus;
use App\Models\Account;
use App\Models\Category;
use App\Models\Debt;
use App\Models\ExpectedIncome;
use App\Models\Expense;
use App\Models\Household;
use App\Models\Income;
use App\Models\RecurringExpense;
use App\Models\SavingsGoal;
use App\Models\User;
use App\Services\BudgetCalculatorService;
use App\Services\HouseholdService;
use App\Services\RecurringExpenseService;
use App\Services\SavingsGoalService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cálculo de dinero disponible (Épica 4, ADR-0014, ADR-0040).
 *
 * El reloj se fija en la fecha de referencia: así los `created_at` caen en
 * "hoy", como pasa cuando alguien configura algo por primera vez.
 */
class BudgetCalculatorServiceTest extends TestCase
{
    use RefreshDatabase;

    /** Día 10 de un mes de 31 días: 10 transcurridos, 22 restantes (incluye hoy). */
    private const REFERENCE = '2026-03-10';

    private Household $household;

    private Account $account;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse(self::REFERENCE.' 12:00:00'));

        $this->owner = User::factory()->create();
        $this->household = app(HouseholdService::class)->createHousehold($this->owner->id, 'Hogar A');
        $this->account = Account::factory()->create([
            'household_id' => $this->household->id,
            'type' => AccountType::Bank->value,
            'initial_balance' => 0,
            'current_balance' => 500000,
            'is_active' => true,
        ]);
    }

    // ===== Caso vacío =====

    public function test_hogar_sin_datos_devuelve_el_plan_en_cero(): void
    {
        $summary = $this->summary();

        $this->assertSame(0.0, $summary['expected_income']);
        $this->assertSame(0.0, $summary['spent']);
        $this->assertSame(0.0, $summary['committed']['total']);
        $this->assertSame(0.0, $summary['plan_available']);
        $this->assertFalse($summary['has_budget']);
        $this->assertFalse($summary['has_expected_income']);
        $this->assertNull($summary['consumed_percent']);
        $this->assertNull($summary['level']);
    }

    // ===== Plan: ingresos esperados =====

    public function test_ingresos_esperados_suman_solo_los_activos(): void
    {
        $this->expectedIncome(3000000);
        $this->expectedIncome(500000, active: false);

        $summary = $this->summary();

        $this->assertSame(3000000.0, $summary['expected_income']);
        $this->assertSame(3000000.0, $summary['expected_income_monthly']);
        $this->assertTrue($summary['has_expected_income']);
    }

    public function test_no_se_duplica_el_salario_cuando_ya_esta_registrado(): void
    {
        $this->expectedIncome(3000000);
        $this->income(3000000, self::REFERENCE);

        // Esperado 3M + registrado 3M ≠ 6M: es el mismo dinero.
        $this->assertSame(3000000.0, $this->summary()['expected_income']);
    }

    public function test_si_entra_mas_de_lo_esperado_manda_lo_registrado(): void
    {
        $this->expectedIncome(3000000);
        $this->income(4200000, self::REFERENCE);

        $this->assertSame(4200000.0, $this->summary()['expected_income']);
    }

    public function test_sin_ingresos_esperados_configurados_se_usan_los_registrados(): void
    {
        $this->income(1800000, self::REFERENCE);

        $summary = $this->summary();

        $this->assertSame(1800000.0, $summary['expected_income']);
        $this->assertFalse($summary['has_expected_income']);
    }

    // ===== Plan: lo que sobraría =====

    public function test_el_plan_resta_gastado_y_comprometido_pero_no_el_presupuesto(): void
    {
        $this->expectedIncome(3000000);
        $this->budget(2000000);                       // presupuesto total del mes
        $this->expense(500000, self::REFERENCE);      // ya gastado

        $summary = $this->summary();

        // El presupuesto reparte lo que puedes gastar, no lo reduce (ADR-0040):
        // queda como dato, fuera del comprometido.
        $this->assertSame(1500000.0, $summary['budget_remaining']);
        $this->assertSame(0.0, $summary['committed']['total']);
        $this->assertSame(2500000.0, $summary['plan_available']);
    }

    public function test_sin_obligaciones_configuradas_los_seams_estan_en_cero(): void
    {
        $summary = $this->summary();

        $this->assertSame(0.0, $summary['committed']['fixed_expenses']);
        $this->assertSame(0.0, $summary['committed']['recurring']);
        $this->assertSame(0.0, $summary['committed']['debt']);
        $this->assertSame(0.0, $summary['committed']['savings']);
    }

    public function test_recurrentes_rellenan_los_seams_de_fijos_y_obligaciones(): void
    {
        $this->expectedIncome(3000000);
        $this->recurring('Arriendo', 1200000, Frequency::Monthly, '2026-03-20'); // fijo
        $this->recurring('SOAT', 600000, Frequency::Yearly, '2026-03-25');       // obligación

        $summary = $this->summary();

        $this->assertSame(1200000.0, $summary['committed']['fixed_expenses']);
        $this->assertSame(600000.0, $summary['committed']['recurring']);
        $this->assertSame(1200000.0, $summary['plan_available']);
    }

    public function test_recurrentes_de_la_ventana_semana_solo_cuentan_si_ocurren_en_ella(): void
    {
        $this->expectedIncome(3000000);
        // Semana del 9 al 15 de marzo de 2026 (referencia: 10/03).
        $this->recurring('Arriendo', 1200000, Frequency::Monthly, '2026-03-20'); // fuera de la semana
        $this->recurring('Mercado', 50000, Frequency::Weekly, '2026-03-12');     // dentro (12 y no más)

        $summary = $this->summary(BudgetScope::Week);

        $this->assertSame(50000.0, $summary['committed']['fixed_expenses']);
        $this->assertSame(0.0, $summary['committed']['recurring']);
    }

    public function test_recurrente_marcado_pagado_no_se_duplica_con_el_gasto_registrado(): void
    {
        $this->expectedIncome(3000000);
        $recurring = $this->recurring('Arriendo', 1200000, Frequency::Monthly, '2026-03-20');
        // "Marcar pagado" solo registra el gasto si hay cuenta asociada.
        $recurring->update(['account_id' => $this->account->id]);

        $this->assertSame(1200000.0, $this->summary()['committed']['fixed_expenses']);
        $this->assertSame(1800000.0, $this->summary()['plan_available']);

        app(RecurringExpenseService::class)
            ->markAsPaid($recurring, $this->owner, Carbon::parse(self::REFERENCE));

        // El gasto quedó registrado y salió del comprometido: mismo dinero,
        // contado una sola vez.
        $summary = $this->summary();
        $this->assertSame(1200000.0, $summary['spent']);
        $this->assertSame(0.0, $summary['committed']['fixed_expenses']);
        $this->assertSame(1800000.0, $summary['plan_available']);
    }

    public function test_recurrentes_inactivos_no_cuentan(): void
    {
        $this->expectedIncome(3000000);
        $this->recurring('Arriendo', 1200000, Frequency::Monthly, '2026-03-20', false);

        $this->assertSame(0.0, $this->summary()['committed']['total']);
    }

    public function test_el_plan_puede_ser_negativo(): void
    {
        $this->expectedIncome(1000000);
        $this->expense(1500000, self::REFERENCE);

        $this->assertSame(-500000.0, $this->summary()['plan_available']);
    }

    // ===== Presupuesto total vs. por categoría (sin doble conteo) =====

    public function test_presupuesto_restante_toma_el_mayor_entre_total_y_categorias(): void
    {
        $this->budget(2000000);                                   // total
        $this->budget(800000, $this->category('Alimentación')->id);
        $this->budget(300000, $this->category('Transporte')->id);

        $summary = $this->summary();

        // Total pendiente = 2.000.000; categorías pendientes = 1.100.000.
        // Se toma el mayor, no la suma (2.000.000, no 3.100.000).
        $this->assertSame(2000000.0, $summary['budget_remaining']);
        $this->assertSame(2000000.0, $summary['budget_defined']);
    }

    public function test_si_las_categorias_superan_al_total_manda_la_suma_de_categorias(): void
    {
        $this->budget(500000);                                       // total pequeño
        $this->budget(800000, $this->category('Alimentación')->id);
        $this->budget(300000, $this->category('Transporte')->id);

        $summary = $this->summary();

        $this->assertSame(1100000.0, $summary['budget_remaining']);
        $this->assertSame(1100000.0, $summary['budget_defined']);
    }

    public function test_el_presupuesto_gastado_de_mas_no_deja_restante_negativo(): void
    {
        $this->budget(1000000);
        $this->expense(1500000, self::REFERENCE);

        $this->assertSame(0.0, $this->summary()['budget_remaining']);
    }

    // ===== Alertas 80 % / 100 % =====

    public function test_categoria_al_80_por_ciento_genera_aviso(): void
    {
        $categoria = $this->category('Alimentación');
        $this->budget(1000000, $categoria->id);
        $this->expense(800000, self::REFERENCE, $categoria->id);

        $summary = $this->summary();
        $row = $summary['categories']->firstWhere('category_id', $categoria->id);

        $this->assertSame(80.0, $row['percent']);
        $this->assertSame(BudgetAlertLevel::Warning, $row['level']);
        $this->assertCount(1, $summary['warnings']);
        $this->assertCount(0, $summary['exceeded']);
    }

    public function test_categoria_al_100_por_ciento_se_marca_excedida(): void
    {
        $categoria = $this->category('Transporte');
        $this->budget(300000, $categoria->id);
        $this->expense(360000, self::REFERENCE, $categoria->id);

        $summary = $this->summary();
        $row = $summary['categories']->firstWhere('category_id', $categoria->id);

        $this->assertSame(120.0, $row['percent']);
        $this->assertSame(BudgetAlertLevel::Exceeded, $row['level']);
        $this->assertSame(60000.0, $row['overspent']);
        $this->assertSame(0.0, $row['remaining']);
        $this->assertCount(1, $summary['exceeded']);
    }

    public function test_categoria_por_debajo_del_80_esta_en_rango(): void
    {
        $categoria = $this->category('Ocio');
        $this->budget(200000, $categoria->id);
        $this->expense(100000, self::REFERENCE, $categoria->id);

        $row = $this->summary()['categories']->firstWhere('category_id', $categoria->id);

        $this->assertSame(50.0, $row['percent']);
        $this->assertSame(BudgetAlertLevel::Ok, $row['level']);
        $this->assertSame(100000.0, $row['remaining']);
    }

    public function test_el_gasto_de_una_categoria_no_afecta_a_otra(): void
    {
        $alimentacion = $this->category('Alimentación');
        $transporte = $this->category('Transporte');
        $this->budget(500000, $alimentacion->id);
        $this->budget(500000, $transporte->id);

        $this->expense(400000, self::REFERENCE, $alimentacion->id);

        $rows = $this->summary()['categories']->keyBy('category_id');

        $this->assertSame(400000.0, $rows[$alimentacion->id]['spent']);
        $this->assertSame(0.0, $rows[$transporte->id]['spent']);
    }

    // ===== Días y ritmo de gasto =====

    public function test_dias_del_mes_en_curso(): void
    {
        $summary = $this->summary();

        $this->assertSame(31, $summary['days_total']);   // marzo
        $this->assertSame(10, $summary['days_elapsed']);
        $this->assertSame(22, $summary['days_remaining']); // incluye hoy
    }

    public function test_tendencia_marca_exceso_cuando_el_ritmo_se_dispara(): void
    {
        $this->budget(1000000);
        $this->expense(900000, self::REFERENCE); // 90.000/día × 31 ≈ 2.790.000

        $summary = $this->summary();

        $this->assertSame('over', $summary['trend']);
        $this->assertSame(2790000.0, $summary['projected_spend']);
    }

    public function test_tendencia_marca_ahorro_cuando_el_ritmo_es_bajo(): void
    {
        $this->budget(3000000);
        $this->expense(100000, self::REFERENCE);

        $this->assertSame('under', $this->summary()['trend']);
    }

    // ===== Períodos: semana / mes / próximo mes =====

    public function test_la_semana_prorratea_el_presupuesto_mensual(): void
    {
        $categoria = $this->category('Alimentación');
        $this->budget(3100000, $categoria->id); // 100.000 por día en un mes de 31

        $summary = $this->summary(BudgetScope::Week);

        $this->assertSame(7, $summary['days_total']);
        $this->assertTrue($summary['prorated']);
        // 3.100.000 × 7/31 = 700.000
        $this->assertSame(700000.0, $summary['categories']->first()['budget']);
    }

    public function test_la_semana_solo_cuenta_los_gastos_de_esa_semana(): void
    {
        // 2026-03-10 es martes: la semana va del 9 al 15 de marzo.
        $this->expense(50000, '2026-03-10');
        $this->expense(80000, '2026-03-02'); // semana anterior

        $summary = $this->summary(BudgetScope::Week);

        $this->assertSame('2026-03-09', $summary['from']->format('Y-m-d'));
        $this->assertSame('2026-03-15', $summary['to']->format('Y-m-d'));
        $this->assertSame(50000.0, $summary['spent']);
    }

    public function test_la_semana_tambien_trae_la_liquidez_de_hoy(): void
    {
        $this->expectedIncome(3000000, day: 15);

        $week = $this->summary(BudgetScope::Week)['liquidity'];

        // La liquidez no depende de la ventana consultada: es la de hoy.
        $this->assertEquals($this->liquidity(), $week);
    }

    public function test_proximo_mes_usa_los_presupuestos_de_ese_mes(): void
    {
        $this->budget(1000000, null, 2026, 3); // marzo
        $this->budget(1500000, null, 2026, 4); // abril

        $summary = $this->summary(BudgetScope::NextMonth);

        $this->assertSame(2026, $summary['year']);
        $this->assertSame(4, $summary['month']);
        $this->assertSame(1500000.0, $summary['budget_defined']);
        $this->assertSame(30, $summary['days_total']); // abril
        $this->assertSame(0, $summary['days_elapsed']);
        $this->assertSame(30, $summary['days_remaining']);
    }

    public function test_proximo_mes_es_una_proyeccion_sin_liquidez(): void
    {
        $this->expectedIncome(3000000);
        $this->expense(900000, self::REFERENCE); // gasto de marzo, no de abril

        $summary = $this->summary(BudgetScope::NextMonth);

        $this->assertSame(3000000.0, $summary['expected_income']);
        $this->assertSame(0.0, $summary['spent']);
        $this->assertSame(3000000.0, $summary['plan_available']);
        // Nadie gasta hoy la plata de un mes que no ha empezado.
        $this->assertNull($summary['liquidity']);
    }

    // ===== Liquidez: "puedes gastar hoy" (ADR-0040) =====

    public function test_antes_del_cobro_solo_cuenta_la_plata_que_tienes(): void
    {
        // El caso que motivó ADR-0040: gana 3M, cobra el 15, hoy es 10 y
        // tiene 100.000 en la cuenta.
        $this->setBalance(100000);
        $this->expectedIncome(3000000, day: 15);

        $liquidity = $this->liquidity();

        $this->assertSame('2026-03-15', $liquidity['payday']->toDateString());
        $this->assertSame('2026-03-14', $liquidity['until']->toDateString());
        $this->assertSame(5, $liquidity['days']);                // 10, 11, 12, 13 y 14
        $this->assertSame(100000.0, $liquidity['available']);
        $this->assertSame(20000.0, $liquidity['daily_allowance']);
        $this->assertSame('ok', $liquidity['status']);
        $this->assertSame('cash', $liquidity['limited_by']);
    }

    public function test_despues_del_cobro_la_plata_tiene_que_alcanzar_hasta_el_siguiente(): void
    {
        $this->expectedIncome(3000000, day: 15);
        $this->income(3000000, '2026-03-15');
        $this->setBalance(3100000);

        $liquidity = $this->liquidity('2026-03-16');

        $this->assertSame('2026-04-15', $liquidity['payday']->toDateString());
        $this->assertSame(30, $liquidity['days']);               // 16/03 al 14/04
        $this->assertSame(3100000.0, $liquidity['available']);
        $this->assertSame(103333.33, $liquidity['daily_allowance']);
        $this->assertSame([], $liquidity['pending_incomes']);
    }

    public function test_los_pagos_que_vencen_antes_del_cobro_se_reservan(): void
    {
        $this->setBalance(300000);
        $this->expectedIncome(3000000, day: 15);
        $this->recurring('Internet', 100000, Frequency::Monthly, '2026-03-12');   // antes del cobro
        $this->recurring('Arriendo', 1200000, Frequency::Monthly, '2026-03-20');  // después: lo cubre el salario

        $liquidity = $this->liquidity();

        $this->assertSame(100000.0, $liquidity['reserved']['fixed_expenses']);
        $this->assertSame(100000.0, $liquidity['reserved']['total']);
        $this->assertSame(200000.0, $liquidity['available']);
        $this->assertSame(40000.0, $liquidity['daily_allowance']);
    }

    public function test_una_obligacion_vencida_se_reserva_solo_si_vencio_estando_registrada(): void
    {
        $this->setBalance(1000000);
        $this->expectedIncome(3000000, day: 15);

        // Registrada el 1.º, vencía el 5 y no se marcó pagada: se sigue debiendo.
        $this->travelTo(Carbon::parse('2026-03-01 09:00:00'));
        $this->recurring('Gimnasio', 90000, Frequency::Monthly, '2026-03-05');
        $this->travelTo(Carbon::parse(self::REFERENCE.' 12:00:00'));

        // Registrada hoy con una fecha pasada: casi siempre es un pago que ya
        // se hizo antes de empezar a usar Finlia.
        $this->recurring('Plan celular', 60000, Frequency::Monthly, '2026-03-05');

        $this->assertSame(90000.0, $this->liquidity()['reserved']['fixed_expenses']);
    }

    public function test_la_cuota_de_deuda_antes_del_cobro_se_reserva(): void
    {
        $this->setBalance(500000);
        $this->expectedIncome(3000000, day: 15);
        $this->debt(dueDay: 12, installment: 150000);   // vence antes del cobro
        $this->debt(dueDay: 25, installment: 400000);   // vence después

        $liquidity = $this->liquidity();

        $this->assertSame(150000.0, $liquidity['reserved']['debt']);
        $this->assertSame(350000.0, $liquidity['available']);
    }

    public function test_la_cuota_vencida_antes_de_registrar_la_deuda_no_se_reserva(): void
    {
        $this->setBalance(500000);
        $this->expectedIncome(3000000, day: 15);
        $this->debt(dueDay: 5, installment: 150000);    // vencía el 5; la deuda se registró hoy

        $this->assertSame(0.0, $this->liquidity()['reserved']['debt']);
    }

    public function test_pago_atrasado_queda_pendiente_y_no_se_cuenta(): void
    {
        $this->travelTo(Carbon::parse('2026-03-01 09:00:00'));
        $this->expectedIncome(3000000, day: 5, name: 'Salario');
        $this->travelTo(Carbon::parse(self::REFERENCE.' 12:00:00'));

        $liquidity = $this->liquidity();

        $this->assertCount(1, $liquidity['pending_incomes']);
        $this->assertSame('Salario', $liquidity['pending_incomes'][0]['name']);
        $this->assertSame('2026-03-05', $liquidity['pending_incomes'][0]['date']->toDateString());
        $this->assertFalse($liquidity['pending_incomes'][0]['is_today']);
        // Mientras no llegue, lo de hoy tiene que alcanzar hasta el cobro siguiente.
        $this->assertSame('2026-04-05', $liquidity['payday']->toDateString());
        $this->assertSame(26, $liquidity['days']);
        $this->assertSame(500000.0, $liquidity['available']);
    }

    public function test_hoy_es_dia_de_cobro_y_aun_no_llega(): void
    {
        $this->travelTo(Carbon::parse('2026-03-01 09:00:00'));
        $this->expectedIncome(3000000, day: 10);
        $this->travelTo(Carbon::parse(self::REFERENCE.' 12:00:00'));

        $liquidity = $this->liquidity();

        $this->assertTrue($liquidity['pending_incomes'][0]['is_today']);
        $this->assertSame('2026-04-10', $liquidity['payday']->toDateString());
    }

    public function test_un_ingreso_configurado_despues_de_su_fecha_no_queda_pendiente(): void
    {
        // Configurado hoy, cobra el 5: ese pago ya es parte del saldo inicial.
        $this->expectedIncome(3000000, day: 5);

        $this->assertSame([], $this->liquidity()['pending_incomes']);
    }

    public function test_un_pago_registrado_no_queda_pendiente(): void
    {
        $this->travelTo(Carbon::parse('2026-03-01 09:00:00'));
        $this->expectedIncome(3000000, day: 5);
        $this->travelTo(Carbon::parse(self::REFERENCE.' 12:00:00'));
        $this->income(3000000, '2026-03-05');

        $this->assertSame([], $this->liquidity()['pending_incomes']);
    }

    public function test_pago_adelantado_mueve_el_horizonte_al_cobro_siguiente(): void
    {
        // Cobra el 12 pero le pagaron el 9: esa plata ya está en el saldo y
        // tiene que alcanzar hasta el 12 de abril, no hasta pasado mañana.
        $this->expectedIncome(3000000, day: 12);
        $this->income(3000000, '2026-03-09');
        $this->setBalance(3200000);

        $liquidity = $this->liquidity();

        $this->assertSame('2026-04-12', $liquidity['payday']->toDateString());
        $this->assertSame(33, $liquidity['days']);
    }

    public function test_sin_dia_de_cobro_el_horizonte_es_fin_de_mes(): void
    {
        $this->expectedIncome(3000000, name: 'Salario');

        $liquidity = $this->liquidity();

        $this->assertFalse($liquidity['payday_known']);
        $this->assertSame('Salario', $liquidity['payday_income']);
        $this->assertSame('2026-04-01', $liquidity['payday']->toDateString());
        $this->assertSame(22, $liquidity['days']);
    }

    public function test_sin_ingresos_esperados_cuenta_el_saldo_hasta_fin_de_mes_y_sin_tope(): void
    {
        $liquidity = $this->liquidity();

        $this->assertFalse($liquidity['has_expected_income']);
        $this->assertNull($liquidity['plan_limit']);
        $this->assertSame(22, $liquidity['days']);
        $this->assertSame(22727.27, $liquidity['daily_allowance']); // 500.000 / 22
    }

    public function test_dos_quincenas_iguales_se_turnan(): void
    {
        $this->expectedIncome(1500000, day: 15, name: 'Primera quincena');
        $this->expectedIncome(1500000, day: 30, name: 'Segunda quincena');

        $this->assertSame('2026-03-15', $this->liquidity()['payday']->toDateString());

        $this->income(1500000, '2026-03-15');
        $this->assertSame('2026-03-30', $this->liquidity('2026-03-16')['payday']->toDateString());
    }

    public function test_un_ingreso_menor_no_acorta_el_horizonte(): void
    {
        // Si el arriendo que cobra se atrasa, no puede dejar al hogar corto.
        $this->expectedIncome(3000000, day: 20, name: 'Salario');
        $this->expectedIncome(200000, day: 12, name: 'Arriendo del garaje');

        $liquidity = $this->liquidity();

        $this->assertSame('2026-03-20', $liquidity['payday']->toDateString());
        $this->assertSame('Salario', $liquidity['payday_income']);
    }

    public function test_lo_apartado_en_metas_no_se_puede_gastar(): void
    {
        $this->goal(saved: 200000);
        $this->goal(saved: 900000, status: SavingsGoalStatus::Archived);

        $liquidity = $this->liquidity();

        // Los aportes no mueven cuentas (ADR-0025): esa plata sigue en el saldo.
        $this->assertSame(200000.0, $liquidity['set_aside']);
        $this->assertSame(300000.0, $liquidity['available']);
    }

    public function test_el_ahorro_programado_se_reparte_en_el_ciclo_de_cobro(): void
    {
        $this->expectedIncome(3000000, day: 15);
        $this->goal(commitment: 280000);

        // Ciclo del 15/02 al 15/03 (28 días); faltan 5: 280.000 × 5/28.
        $this->assertSame(50000.0, $this->liquidity()['reserved']['savings']);
    }

    public function test_el_aporte_del_ciclo_ya_no_se_reserva_pero_queda_apartado(): void
    {
        $this->expectedIncome(3000000, day: 15);
        $goal = $this->goal(commitment: 280000);
        app(SavingsGoalService::class)->registerContribution($goal, [
            'amount' => 280000,
            'date' => '2026-03-01',
            'type' => SavingsGoalContributionType::Deposit->value,
        ]);

        $liquidity = $this->liquidity();

        $this->assertSame(0.0, $liquidity['reserved']['savings']);
        $this->assertSame(280000.0, $liquidity['set_aside']);
    }

    public function test_mucha_plata_en_cuentas_la_limita_el_plan_del_mes(): void
    {
        // Ahorros fuera de una meta o un salario adelantado no pueden
        // convertirse en "gasta 4 millones al día".
        $this->setBalance(20000000);
        $this->expectedIncome(2200000, day: 15);

        $liquidity = $this->liquidity();

        $this->assertSame('plan', $liquidity['limited_by']);
        $this->assertSame(500000.0, $liquidity['plan_limit']);       // 2.200.000 / 22 × 5
        $this->assertSame(500000.0, $liquidity['available']);
        $this->assertSame(100000.0, $liquidity['daily_allowance']);
    }

    public function test_una_tarjeta_de_credito_solo_resta(): void
    {
        $this->card(5000000);    // cupo: plata prestada, no suma
        $this->card(-300000);    // lo que se debe en la tarjeta sí resta

        $this->assertSame(200000.0, $this->liquidity()['current_balance']);
    }

    public function test_si_el_saldo_no_cubre_lo_que_vence_hay_faltante(): void
    {
        $this->setBalance(100000);
        $this->expectedIncome(3000000, day: 15);
        $this->recurring('Servicios', 300000, Frequency::Monthly, '2026-03-12');

        $liquidity = $this->liquidity();

        $this->assertSame('short', $liquidity['status']);
        $this->assertSame(200000.0, $liquidity['shortfall']);
        $this->assertSame(0.0, $liquidity['daily_allowance']);
    }

    public function test_pasarse_del_plan_se_distingue_de_no_tener_saldo(): void
    {
        $this->expectedIncome(1000000, day: 15);
        $this->expense(1500000, self::REFERENCE);

        $liquidity = $this->liquidity();

        $this->assertSame('over_plan', $liquidity['status']);
        $this->assertSame(500000.0, $liquidity['shortfall']);
        $this->assertSame(0.0, $liquidity['daily_allowance']);
    }

    // ===== Aislamiento entre hogares =====

    public function test_no_mezcla_datos_de_otro_hogar(): void
    {
        $intruso = User::factory()->create();
        $otro = app(HouseholdService::class)->createHousehold($intruso->id, 'Hogar B');
        $otraCuenta = Account::factory()->create(['household_id' => $otro->id, 'current_balance' => 7000000]);

        $otro->expectedIncomes()->create(['name' => 'Salario ajeno', 'amount' => 9000000, 'day_of_month' => 12]);
        $otro->budgets()->create([
            'category_id' => null, 'amount' => 5000000, 'period' => 'monthly', 'year' => 2026, 'month' => 3,
        ]);
        Expense::factory()->create([
            'household_id' => $otro->id,
            'account_id' => $otraCuenta->id,
            'amount' => 400000,
            'date' => self::REFERENCE,
        ]);
        SavingsGoal::factory()->create(['household_id' => $otro->id, 'current_amount' => 800000]);

        $summary = $this->summary();

        $this->assertSame(0.0, $summary['expected_income']);
        $this->assertSame(0.0, $summary['spent']);
        $this->assertSame(0.0, $summary['committed']['total']);
        $this->assertSame(500000.0, $summary['liquidity']['current_balance']);
        $this->assertSame(0.0, $summary['liquidity']['set_aside']);
        $this->assertFalse($summary['liquidity']['payday_known']);
    }

    // ===== Helpers =====

    /**
     * @return array<string, mixed>
     */
    private function summary(BudgetScope $scope = BudgetScope::Month): array
    {
        return app(BudgetCalculatorService::class)->summary(
            $this->household->id,
            $scope,
            Carbon::parse(self::REFERENCE),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function liquidity(string $reference = self::REFERENCE): array
    {
        return app(BudgetCalculatorService::class)->liquidity($this->household->id, Carbon::parse($reference));
    }

    private function setBalance(float $balance): void
    {
        $this->account->forceFill(['current_balance' => $balance])->save();
    }

    private function budget(float $amount, ?int $categoryId = null, int $year = 2026, int $month = 3): void
    {
        $this->household->budgets()->create([
            'category_id' => $categoryId,
            'amount' => $amount,
            'period' => 'monthly',
            'year' => $year,
            'month' => $month,
        ]);
    }

    private function expectedIncome(float $amount, bool $active = true, ?int $day = null, ?string $name = null): ExpectedIncome
    {
        return $this->household->expectedIncomes()->create([
            'name' => $name ?? 'Fuente '.$amount,
            'amount' => $amount,
            'day_of_month' => $day,
            'is_active' => $active,
        ]);
    }

    private function recurring(string $name, float $amount, Frequency $frequency, string $nextDate, bool $active = true): RecurringExpense
    {
        return $this->household->recurringExpenses()->create([
            'name' => $name,
            'amount' => $amount,
            'frequency' => $frequency->value,
            'next_date' => $nextDate,
            'is_active' => $active,
        ]);
    }

    private function debt(int $dueDay, float $installment): Debt
    {
        return Debt::factory()->create([
            'household_id' => $this->household->id,
            'current_balance' => 5000000,
            'planned_payment' => $installment,
            'due_day' => $dueDay,
        ]);
    }

    private function goal(
        float $saved = 0,
        ?float $commitment = null,
        SavingsGoalStatus $status = SavingsGoalStatus::Active,
    ): SavingsGoal {
        return SavingsGoal::factory()->create([
            'household_id' => $this->household->id,
            'target_amount' => 10000000,
            'current_amount' => $saved,
            'monthly_commitment' => $commitment,
            'status' => $status->value,
        ]);
    }

    private function card(float $balance): void
    {
        Account::factory()->create([
            'household_id' => $this->household->id,
            'type' => AccountType::CreditCard->value,
            'current_balance' => $balance,
            'is_active' => true,
        ]);
    }

    private function expense(float $amount, string $date, ?int $categoryId = null): void
    {
        Expense::factory()->create([
            'household_id' => $this->household->id,
            'user_id' => $this->owner->id,
            'account_id' => $this->account->id,
            'category_id' => $categoryId,
            'amount' => $amount,
            'date' => $date,
        ]);
    }

    private function income(float $amount, string $date): void
    {
        Income::factory()->create([
            'household_id' => $this->household->id,
            'user_id' => $this->owner->id,
            'account_id' => $this->account->id,
            'amount' => $amount,
            'date' => $date,
        ]);
    }

    private function category(string $name): Category
    {
        return Category::create([
            'household_id' => $this->household->id,
            'name' => $name,
            'type' => CategoryType::Expense->value,
            'is_default' => false,
            'color' => '#0f766e',
        ]);
    }
}
