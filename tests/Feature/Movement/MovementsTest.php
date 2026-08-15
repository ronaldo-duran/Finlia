<?php

namespace Tests\Feature\Movement;

use App\Enums\CategoryType;
use App\Models\Account;
use App\Models\Category;
use App\Models\Household;
use App\Models\User;
use App\Services\HouseholdService;
use App\Services\MovementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MovementsTest extends TestCase
{
    use RefreshDatabase;

    private function setupWithMovements(): array
    {
        $owner = User::factory()->create();
        $household = app(HouseholdService::class)->createHousehold($owner->id, 'Hogar A');
        $account = Account::factory()->withInitialBalance(500000)->create(['household_id' => $household->id]);

        $incomeCat = Category::create(['name' => 'Salario', 'type' => CategoryType::Income->value, 'household_id' => null, 'is_default' => true]);
        $expenseCat = Category::create(['name' => 'Mercado', 'type' => CategoryType::Expense->value, 'household_id' => null, 'is_default' => true]);

        $service = app(MovementService::class);
        $householdModel = Household::find($household->id);

        $service->createIncome(household: $householdModel, user: $owner, data: [
            'amount' => 800000, 'account_id' => $account->id, 'category_id' => $incomeCat->id,
            'date' => now()->format('Y-m-d'), 'description' => 'Salario único prueba',
        ]);
        $service->createExpense(household: $householdModel, user: $owner, data: [
            'amount' => 120000, 'account_id' => $account->id, 'category_id' => $expenseCat->id,
            'date' => now()->format('Y-m-d'), 'description' => 'Mercado único prueba',
        ]);

        return [$owner, $household];
    }

    public function test_usuario_lista_sus_movimientos(): void
    {
        [$owner] = $this->setupWithMovements();

        $this->actingAs($owner)->get(route('movements.index'))
            ->assertOk()
            ->assertSee('Salario único prueba')
            ->assertSee('Mercado único prueba');
    }

    public function test_filtra_solo_gastos(): void
    {
        [$owner] = $this->setupWithMovements();

        $this->actingAs($owner)->get(route('movements.index', ['type' => 'expense']))
            ->assertOk()
            ->assertSee('Mercado único prueba')
            ->assertDontSee('Salario único prueba');
    }

    public function test_filtra_solo_ingresos(): void
    {
        [$owner] = $this->setupWithMovements();

        $this->actingAs($owner)->get(route('movements.index', ['type' => 'income']))
            ->assertOk()
            ->assertSee('Salario único prueba')
            ->assertDontSee('Mercado único prueba');
    }

    // ===== Aislamiento multi-hogar =====

    public function test_usuario_ajeno_no_ve_movimientos_de_otro_hogar(): void
    {
        [$owner] = $this->setupWithMovements();

        $intruder = User::factory()->create();
        app(HouseholdService::class)->createHousehold($intruder->id, 'Hogar Intruso');

        $this->actingAs($intruder)->get(route('movements.index'))
            ->assertOk()
            ->assertDontSee('Salario único prueba')
            ->assertDontSee('Mercado único prueba');
    }
}
