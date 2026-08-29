<?php

namespace Tests\Unit;

use App\Enums\CategoryType;
use App\Models\Account;
use App\Models\Category;
use App\Models\Expense;
use App\Models\Income;
use App\Models\User;
use App\Services\HouseholdService;
use App\Services\MovementSummaryService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MovementSummaryServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_month_totals_vacio_cuando_no_hay_movimientos(): void
    {
        $owner = User::factory()->create();
        $household = app(HouseholdService::class)->createHousehold($owner->id, 'Hogar A');

        $now = Carbon::now();
        $totals = app(MovementSummaryService::class)->monthTotals($household->id, $now->year, $now->month);

        $this->assertSame(0.0, $totals['incomes']);
        $this->assertSame(0.0, $totals['expenses']);
        $this->assertSame(0.0, $totals['balance']);
    }

    public function test_month_totals_agrega_ingresos_y_gastos_del_mes(): void
    {
        [$householdId, $accountId] = $this->setupHouseholdWithAccount();
        $today = Carbon::now()->format('Y-m-d');

        Income::factory()->create(['household_id' => $householdId, 'account_id' => $accountId, 'amount' => 500000, 'date' => $today]);
        Expense::factory()->create(['household_id' => $householdId, 'account_id' => $accountId, 'amount' => 200000, 'date' => $today]);

        $now = Carbon::now();
        $totals = app(MovementSummaryService::class)->monthTotals($householdId, $now->year, $now->month);

        $this->assertSame(500000.0, $totals['incomes']);
        $this->assertSame(200000.0, $totals['expenses']);
        $this->assertSame(300000.0, $totals['balance']);
    }

    public function test_expenses_by_category_agrupa_y_suma(): void
    {
        [$householdId, $accountId] = $this->setupHouseholdWithAccount();
        $today = Carbon::now();

        $cat1 = Category::create(['name' => 'Comida', 'type' => CategoryType::Expense->value, 'household_id' => null, 'is_default' => true, 'color' => '#aaa']);
        $cat2 = Category::create(['name' => 'Transporte', 'type' => CategoryType::Expense->value, 'household_id' => null, 'is_default' => true, 'color' => '#bbb']);

        Expense::factory()->create(['household_id' => $householdId, 'account_id' => $accountId, 'category_id' => $cat1->id, 'amount' => 30000, 'date' => $today->format('Y-m-d')]);
        Expense::factory()->create(['household_id' => $householdId, 'account_id' => $accountId, 'category_id' => $cat1->id, 'amount' => 20000, 'date' => $today->format('Y-m-d')]);
        Expense::factory()->create(['household_id' => $householdId, 'account_id' => $accountId, 'category_id' => $cat2->id, 'amount' => 10000, 'date' => $today->format('Y-m-d')]);

        $rows = app(MovementSummaryService::class)->expensesByCategory($householdId, $today->copy()->startOfMonth(), $today->copy()->endOfMonth());

        $this->assertCount(2, $rows);
        $byName = $rows->keyBy('name');
        $this->assertSame(50000.0, $byName['Comida']['total']);
        $this->assertSame(10000.0, $byName['Transporte']['total']);
    }

    public function test_expenses_by_category_pliega_el_resto_en_otras(): void
    {
        [$householdId, $accountId] = $this->setupHouseholdWithAccount();
        $today = Carbon::now()->format('Y-m-d');

        // 8 categorías con montos decrecientes: Cat 1 = 80.000 … Cat 8 = 10.000.
        // Top 5 se queda con Cat 1-5; "Otras" = Cat 6+7+8 = 60.000.
        foreach (range(1, 8) as $n) {
            $cat = Category::create([
                'name' => "Cat {$n}", 'type' => CategoryType::Expense->value,
                'household_id' => null, 'is_default' => true,
            ]);

            Expense::factory()->create([
                'household_id' => $householdId,
                'account_id' => $accountId,
                'category_id' => $cat->id,
                'amount' => 90000 - $n * 10000,
                'date' => $today,
            ]);
        }

        $rows = app(MovementSummaryService::class)->expensesByCategory(
            $householdId,
            Carbon::now()->startOfMonth(),
            Carbon::now()->endOfMonth(),
            top: 5,
        );

        // Top 5 + "Otras" = 6 filas; el resto sumado y en gris neutro.
        $this->assertCount(6, $rows);
        $this->assertSame('Cat 1', $rows[0]['name']);
        $this->assertSame('Cat 5', $rows[4]['name']);
        $this->assertSame('Otras', $rows[5]['name']);
        $this->assertSame(60000.0, $rows[5]['total']);
        $this->assertSame('#adb5bd', $rows[5]['color']);
    }

    public function test_expenses_by_category_sin_exceso_no_inventa_fila_otras(): void
    {
        [$householdId, $accountId] = $this->setupHouseholdWithAccount();
        $today = Carbon::now()->format('Y-m-d');

        $cat = Category::create(['name' => 'Comida', 'type' => CategoryType::Expense->value, 'household_id' => null, 'is_default' => true]);
        Expense::factory()->create(['household_id' => $householdId, 'account_id' => $accountId, 'category_id' => $cat->id, 'amount' => 30000, 'date' => $today]);

        $rows = app(MovementSummaryService::class)->expensesByCategory(
            $householdId,
            Carbon::now()->startOfMonth(),
            Carbon::now()->endOfMonth(),
            top: 5,
        );

        // Una sola categoría: no aparece una fila "Otras" vacía.
        $this->assertCount(1, $rows);
        $this->assertSame('Comida', $rows[0]['name']);
    }

    private function setupHouseholdWithAccount(): array
    {
        $owner = User::factory()->create();
        $household = app(HouseholdService::class)->createHousehold($owner->id, 'Hogar A');
        $account = Account::factory()->create(['household_id' => $household->id]);

        return [$household->id, $account->id];
    }
}
