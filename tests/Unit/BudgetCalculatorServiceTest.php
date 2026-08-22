<?php

namespace Tests\Unit;

use App\Enums\BudgetAlertLevel;
use App\Enums\BudgetScope;
use App\Enums\CategoryType;
use App\Models\Account;
use App\Models\Category;
use App\Models\Expense;
use App\Models\Household;
use App\Models\Income;
use App\Models\User;
use App\Services\BudgetCalculatorService;
use App\Services\HouseholdService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cálculo de dinero disponible (Épica 4, ADR-0014).
 * Se usa una fecha de referencia fija para que los tests no dependan del día real.
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

        $this->owner = User::factory()->create();
        $this->household = app(HouseholdService::class)->createHousehold($this->owner->id, 'Hogar A');
        $this->account = Account::factory()->create([
            'household_id' => $this->household->id,
            'initial_balance' => 0,
            'current_balance' => 500000,
            'is_active' => true,
        ]);
    }

    // ===== Caso vacío =====

    public function test_hogar_sin_datos_devuelve_todo_en_cero(): void
    {
        $summary = $this->summary();

        $this->assertSame(0.0, $summary['expected_income']);
        $this->assertSame(0.0, $summary['spent']);
        $this->assertSame(0.0, $summary['committed']['total']);
        $this->assertSame(0.0, $summary['available']);
        $this->assertFalse($summary['has_budget']);
        $this->assertFalse($summary['has_expected_income']);
        $this->assertNull($summary['consumed_percent']);
        $this->assertNull($summary['level']);
    }

    // ===== Ingresos esperados =====

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

    // ===== Dinero disponible =====

    public function test_disponible_resta_gastado_y_comprometido(): void
    {
        $this->expectedIncome(3000000);
        $this->budget(2000000);                       // presupuesto total del mes
        $this->expense(500000, self::REFERENCE);      // ya gastado

        $summary = $this->summary();

        // Comprometido = presupuesto aún sin gastar = 2.000.000 − 500.000.
        $this->assertSame(1500000.0, $summary['committed']['budget']);
        $this->assertSame(1500000.0, $summary['committed']['total']);
        // Disponible = 3.000.000 − 500.000 − 1.500.000.
        $this->assertSame(1000000.0, $summary['available']);
    }

    public function test_los_componentes_de_epicas_futuras_estan_en_cero(): void
    {
        $summary = $this->summary();

        $this->assertSame(0.0, $summary['committed']['fixed_expenses']);
        $this->assertSame(0.0, $summary['committed']['recurring']);
        $this->assertSame(0.0, $summary['committed']['debt']);
        $this->assertSame(0.0, $summary['committed']['savings']);
    }

    public function test_disponible_puede_ser_negativo(): void
    {
        $this->expectedIncome(1000000);
        $this->expense(1500000, self::REFERENCE);

        $this->assertSame(-500000.0, $this->summary()['available']);
    }

    public function test_dinero_libre_es_saldo_real_menos_comprometido(): void
    {
        // La cuenta tiene 500.000 de saldo actual.
        $this->budget(300000);

        $summary = $this->summary();

        $this->assertSame(500000.0, $summary['current_balance']);
        $this->assertSame(300000.0, $summary['committed']['total']);
        $this->assertSame(200000.0, $summary['free']);
    }

    public function test_las_cuentas_inactivas_no_cuentan_en_el_balance(): void
    {
        Account::factory()->create([
            'household_id' => $this->household->id,
            'current_balance' => 9000000,
            'is_active' => false,
        ]);

        $this->assertSame(500000.0, $this->summary()['current_balance']);
    }

    // ===== Presupuesto total vs. por categoría (sin doble conteo) =====

    public function test_comprometido_toma_el_mayor_entre_total_y_categorias(): void
    {
        $alimentacion = $this->category('Alimentación');
        $transporte = $this->category('Transporte');

        $this->budget(2000000);                             // total
        $this->budget(800000, $alimentacion->id);
        $this->budget(300000, $transporte->id);

        $summary = $this->summary();

        // Total pendiente = 2.000.000; categorías pendientes = 1.100.000.
        // Se toma el mayor, no la suma (2.000.000, no 3.100.000).
        $this->assertSame(2000000.0, $summary['committed']['budget']);
        $this->assertSame(2000000.0, $summary['budget_defined']);
    }

    public function test_si_las_categorias_superan_al_total_manda_la_suma_de_categorias(): void
    {
        $this->budget(500000);                                       // total pequeño
        $this->budget(800000, $this->category('Alimentación')->id);
        $this->budget(300000, $this->category('Transporte')->id);

        $summary = $this->summary();

        $this->assertSame(1100000.0, $summary['committed']['budget']);
        $this->assertSame(1100000.0, $summary['budget_defined']);
    }

    public function test_el_presupuesto_gastado_de_mas_no_genera_comprometido_negativo(): void
    {
        $this->budget(1000000);
        $this->expense(1500000, self::REFERENCE);

        $this->assertSame(0.0, $this->summary()['committed']['budget']);
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

    public function test_reparto_diario_del_disponible(): void
    {
        $this->expectedIncome(2200000);

        $summary = $this->summary();

        // 2.200.000 / 22 días restantes.
        $this->assertSame(100000.0, $summary['daily_allowance']);
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

    public function test_proximo_mes_proyecta_los_ingresos_esperados_sin_movimientos(): void
    {
        $this->expectedIncome(3000000);
        $this->expense(900000, self::REFERENCE); // gasto de marzo, no de abril

        $summary = $this->summary(BudgetScope::NextMonth);

        $this->assertSame(3000000.0, $summary['expected_income']);
        $this->assertSame(0.0, $summary['spent']);
        $this->assertSame(3000000.0, $summary['available']);
    }

    // ===== Aislamiento entre hogares =====

    public function test_no_mezcla_datos_de_otro_hogar(): void
    {
        $intruso = User::factory()->create();
        $otro = app(HouseholdService::class)->createHousehold($intruso->id, 'Hogar B');
        $otraCuenta = Account::factory()->create(['household_id' => $otro->id, 'current_balance' => 7000000]);

        $otro->expectedIncomes()->create(['name' => 'Salario ajeno', 'amount' => 9000000]);
        $otro->budgets()->create([
            'category_id' => null, 'amount' => 5000000, 'period' => 'monthly', 'year' => 2026, 'month' => 3,
        ]);
        Expense::factory()->create([
            'household_id' => $otro->id,
            'account_id' => $otraCuenta->id,
            'amount' => 400000,
            'date' => self::REFERENCE,
        ]);

        $summary = $this->summary();

        $this->assertSame(0.0, $summary['expected_income']);
        $this->assertSame(0.0, $summary['spent']);
        $this->assertSame(0.0, $summary['committed']['total']);
        $this->assertSame(500000.0, $summary['current_balance']);
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

    private function expectedIncome(float $amount, bool $active = true): void
    {
        $this->household->expectedIncomes()->create([
            'name' => 'Fuente '.$amount,
            'amount' => $amount,
            'is_active' => $active,
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
