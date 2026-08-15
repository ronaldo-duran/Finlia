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

    private function setupHouseholdWithAccount(): array
    {
        $owner = User::factory()->create();
        $household = app(HouseholdService::class)->createHousehold($owner->id, 'Hogar A');
        $account = Account::factory()->create(['household_id' => $household->id]);

        return [$household->id, $account->id];
    }
}
