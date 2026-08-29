<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\ReportPeriod;
use App\Models\Account;
use App\Models\Category;
use App\Models\Expense;
use App\Models\Household;
use App\Models\Income;
use App\Models\User;
use App\Services\HouseholdService;
use App\Services\ReportService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Épica 8: cálculos de ReportService — ventanas comparables, deltas,
 * series mensuales, insights (solo con datos que existen) y exportación.
 */
class ReportServiceTest extends TestCase
{
    use RefreshDatabase;

    private ReportService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(ReportService::class);
    }

    /**
     * Hogar con cuenta y categoría para poder crear movimientos.
     *
     * @return array{0: User, 1: Household, 2: Account, 3: Category}
     */
    private function setupHousehold(string $name = 'Hogar A'): array
    {
        $owner = User::factory()->create();
        $household = app(HouseholdService::class)->createHousehold($owner->id, $name);
        $account = Account::factory()->create(['household_id' => $household->id]);
        $category = Category::create([
            'name' => 'Alimentación',
            'type' => 'expense',
            'household_id' => null,
            'is_default' => true,
        ]);

        return [$owner, $household, $account, $category];
    }

    private function income(int $householdId, int $userId, int $accountId, float $amount, CarbonInterface $date): Income
    {
        return Income::factory()->create([
            'household_id' => $householdId,
            'user_id' => $userId,
            'account_id' => $accountId,
            'amount' => $amount,
            'date' => $date->toDateString(),
        ]);
    }

    private function expense(int $householdId, int $userId, int $accountId, int $categoryId, float $amount, CarbonInterface $date): Expense
    {
        return Expense::factory()->create([
            'household_id' => $householdId,
            'user_id' => $userId,
            'account_id' => $accountId,
            'category_id' => $categoryId,
            'amount' => $amount,
            'date' => $date->toDateString(),
        ]);
    }

    // ===== Ventanas comparables (ReportPeriod) =====

    public function test_mes_actual_se_compara_con_el_mes_anterior(): void
    {
        $window = ReportPeriod::Month->resolve(Carbon::parse('2026-08-15'));

        $this->assertTrue($window['from']->isSameDay(Carbon::parse('2026-08-01')));
        $this->assertTrue($window['to']->isSameDay(Carbon::parse('2026-08-31')));
        $this->assertTrue($window['previous_from']->isSameDay(Carbon::parse('2026-07-01')));
        $this->assertTrue($window['previous_to']->isSameDay(Carbon::parse('2026-07-31')));
    }

    public function test_mes_anterior_se_compara_con_el_anterior_a_aquel(): void
    {
        $window = ReportPeriod::LastMonth->resolve(Carbon::parse('2026-08-15'));

        $this->assertTrue($window['from']->isSameDay(Carbon::parse('2026-07-01')));
        $this->assertTrue($window['previous_from']->isSameDay(Carbon::parse('2026-06-01')));
    }

    public function test_ultimos_3_meses_cubren_el_mes_actual_y_dos_previos(): void
    {
        $window = ReportPeriod::Last3Months->resolve(Carbon::parse('2026-08-15'));

        $this->assertTrue($window['from']->isSameDay(Carbon::parse('2026-06-01')));
        $this->assertTrue($window['to']->isSameDay(Carbon::parse('2026-08-15')));
        // El equivalente anterior: marzo–mayo.
        $this->assertTrue($window['previous_from']->isSameDay(Carbon::parse('2026-03-01')));
        $this->assertTrue($window['previous_to']->isSameDay(Carbon::parse('2026-05-31')));
    }

    public function test_ano_se_compara_con_el_mismo_tramo_del_ano_previo(): void
    {
        $window = ReportPeriod::Year->resolve(Carbon::parse('2026-08-15'));

        $this->assertTrue($window['from']->isSameDay(Carbon::parse('2026-01-01')));
        $this->assertTrue($window['previous_from']->isSameDay(Carbon::parse('2025-01-01')));
        // A la fecha, no el año completo: no se compara contra meses aún no ocurridos.
        $this->assertTrue($window['previous_to']->isSameDay(Carbon::parse('2025-08-15')));
    }

    // ===== Overview =====

    public function test_overview_suma_el_periodo_y_compara_con_el_anterior(): void
    {
        [$owner, $household, $account] = $this->setupHousehold();
        $today = Carbon::now(config('app.timezone'));

        $this->income($household->id, $owner->id, $account->id, 1000000, $today);
        $this->income($household->id, $owner->id, $account->id, 800000, $today->copy()->subMonth());

        $overview = $this->service->overview($household->id, ReportPeriod::Month);

        $this->assertSame(1000000.0, $overview['incomes']);
        $this->assertSame(800000.0, $overview['previous']['incomes']);
        $this->assertSame(200000.0, $overview['deltas']['incomes']['absolute']);
        $this->assertSame(25.0, $overview['deltas']['incomes']['percent']);
        $this->assertSame(0.0, $overview['expenses']);
        $this->assertSame(1000000.0, $overview['balance']);
    }

    public function test_sin_periodo_anterior_el_delta_no_inventa_porcentaje(): void
    {
        [$owner, $household, $account] = $this->setupHousehold();
        $today = Carbon::now(config('app.timezone'));

        $this->income($household->id, $owner->id, $account->id, 500000, $today);

        $overview = $this->service->overview($household->id, ReportPeriod::Month);

        $this->assertNull($overview['deltas']['incomes']['percent']);
        $this->assertSame(500000.0, $overview['deltas']['incomes']['absolute']);
    }

    public function test_monthly_series_devuelve_un_punto_por_mes_cubierto(): void
    {
        [$owner, $household, $account, $category] = $this->setupHousehold();
        $today = Carbon::now(config('app.timezone'));

        $this->expense($household->id, $owner->id, $account->id, $category->id, 100000, $today);
        $this->expense($household->id, $owner->id, $account->id, $category->id, 50000, $today->copy()->subMonth());

        $series = $this->service->monthlySeries(
            $household->id,
            $today->copy()->subMonth()->startOfMonth(),
            $today,
        );

        $this->assertCount(2, $series);
        $this->assertSame(100000.0, $series[1]['expenses']);
        $this->assertSame(50000.0, $series[0]['expenses']);
        $this->assertSame(-100000.0, $series[1]['balance']);
    }

    // ===== Insights =====

    public function test_sin_datos_no_hay_insights(): void
    {
        [, $household] = $this->setupHousehold();

        $this->assertTrue($this->service->insights($household->id, ReportPeriod::Month)->isEmpty());
    }

    public function test_destaca_el_cambio_de_gasto_total_contra_el_periodo_anterior(): void
    {
        [$owner, $household, $account, $category] = $this->setupHousehold();
        $today = Carbon::now(config('app.timezone'));

        $this->expense($household->id, $owner->id, $account->id, $category->id, 100000, $today);
        $this->expense($household->id, $owner->id, $account->id, $category->id, 200000, $today->copy()->subMonth());

        $insights = $this->service->insights($household->id, ReportPeriod::Month);

        $this->assertTrue($insights->contains(fn ($i) => str_contains($i['text'], 'Gastaste $ 100.000,00 menos')));
    }

    public function test_cambios_pequenos_no_generan_insights_de_ruido(): void
    {
        [$owner, $household, $account, $category] = $this->setupHousehold();
        $today = Carbon::now(config('app.timezone'));

        // +4 %: por debajo del umbral del 5 %.
        $this->expense($household->id, $owner->id, $account->id, $category->id, 104000, $today);
        $this->expense($household->id, $owner->id, $account->id, $category->id, 100000, $today->copy()->subMonth());

        $insights = $this->service->insights($household->id, ReportPeriod::Month);

        $this->assertFalse($insights->contains(fn ($i) => str_contains($i['text'], 'Gastaste $')));
    }

    public function test_categoria_con_aumento_relevante_queda_destacada(): void
    {
        [$owner, $household, $account, $category] = $this->setupHousehold();
        $otra = Category::create([
            'name' => 'Transporte', 'type' => 'expense', 'household_id' => null, 'is_default' => true,
        ]);
        $today = Carbon::now(config('app.timezone'));

        // Alimentación +50 %, Transporte igual.
        $this->expense($household->id, $owner->id, $account->id, $category->id, 150000, $today);
        $this->expense($household->id, $owner->id, $account->id, $category->id, 100000, $today->copy()->subMonth());
        $this->expense($household->id, $owner->id, $account->id, $otra->id, 50000, $today);
        $this->expense($household->id, $owner->id, $account->id, $otra->id, 50000, $today->copy()->subMonth());

        $insights = $this->service->insights($household->id, ReportPeriod::Month);

        $this->assertTrue($insights->contains(
            fn ($i) => str_contains($i['text'], 'Alimentación» aumentó 50 %')
        ));
    }

    public function test_categoria_dominante_reporta_su_participacion(): void
    {
        [$owner, $household, $account, $category] = $this->setupHousehold();
        $otra = Category::create([
            'name' => 'Transporte', 'type' => 'expense', 'household_id' => null, 'is_default' => true,
        ]);
        $today = Carbon::now(config('app.timezone'));

        $this->expense($household->id, $owner->id, $account->id, $category->id, 90000, $today);
        $this->expense($household->id, $owner->id, $account->id, $otra->id, 10000, $today);

        $insights = $this->service->insights($household->id, ReportPeriod::Month);

        $this->assertTrue($insights->contains(
            fn ($i) => str_contains($i['text'], 'Alimentación» representa el 90 %')
        ));
    }

    // ===== Exportación =====

    public function test_export_rows_solo_trae_los_movimientos_del_rango(): void
    {
        [$owner, $household, $account, $category] = $this->setupHousehold();
        $today = Carbon::now(config('app.timezone'));

        $dentro = $this->expense($household->id, $owner->id, $account->id, $category->id, 100000, $today);
        $fuera = $this->expense($household->id, $owner->id, $account->id, $category->id, 100000, $today->copy()->subMonths(3));

        $rows = $this->service->exportRows(
            $household->id,
            $today->copy()->startOfMonth(),
            $today->copy()->endOfMonth(),
        );

        $this->assertTrue($rows->contains(fn ($r) => $r['id'] === $dentro->id && $r['type'] === 'expense'));
        $this->assertFalse($rows->contains(fn ($r) => $r['id'] === $fuera->id));
    }
}
