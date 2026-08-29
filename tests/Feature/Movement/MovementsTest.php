<?php

namespace Tests\Feature\Movement;

use App\Enums\CategoryType;
use App\Models\Account;
use App\Models\Category;
use App\Models\Expense;
use App\Models\Household;
use App\Models\Income;
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

    // ===== Paginación "Cargar más" =====

    /**
     * @return array{0: User, 1: Household, 2: Account, 3: Category}
     */
    private function setupForPagination(): array
    {
        $owner = User::factory()->create();
        $household = app(HouseholdService::class)->createHousehold($owner->id, 'Hogar A');
        $account = Account::factory()->create(['household_id' => $household->id]);
        $category = Category::create([
            'name' => 'Mercado', 'type' => CategoryType::Expense->value,
            'household_id' => null, 'is_default' => true,
        ]);

        return [$owner, $household, $account, $category];
    }

    private function expenseOn(User $owner, Household $household, Account $account, Category $category, string $date, string $description): Expense
    {
        return Expense::factory()->create([
            'household_id' => $household->id,
            'user_id' => $owner->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'amount' => 10000,
            'date' => $date,
            'description' => $description,
        ]);
    }

    public function test_usuario_sin_hogar_es_redirigido_a_crear_hogar(): void
    {
        // Guard defensivo (ADR-0011): sin hogar no hay lista que mostrar.
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('movements.index'))
            ->assertRedirect(route('households.create'));
    }

    public function test_la_lista_carga_de_a_20_con_boton_de_cargar_mas(): void
    {
        [$owner, $household, $account, $category] = $this->setupForPagination();

        // 25 gastos en 25 días distintos: "Gasto pag 0" es hoy (el más nuevo).
        foreach (range(0, 24) as $i) {
            $this->expenseOn($owner, $household, $account, $category, now()->subDays($i)->toDateString(), "Gasto pag {$i}");
        }

        // Página 1: los 20 más recientes y el botón para seguir.
        $this->actingAs($owner)->get(route('movements.index'))
            ->assertOk()
            ->assertSee('Cargar más')
            ->assertSee('Gasto pag 19')
            ->assertDontSee('Gasto pag 20');

        // El botón pide la misma ruta con offset y cabecera AJAX.
        $this->actingAs($owner)
            ->get(route('movements.index', ['offset' => 20]), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertSee('Gasto pag 20')
            ->assertSee('Gasto pag 24')
            ->assertDontSee('Gasto pag 19')
            ->assertDontSee('Cargar más'); // no queda nada más allá
    }

    public function test_la_paginacion_nunca_parte_un_dia_por_la_mitad(): void
    {
        [$owner, $household, $account, $category] = $this->setupForPagination();

        $ayer = now()->subDay()->toDateString();
        $anteayer = now()->subDays(2)->toDateString();

        $this->expenseOn($owner, $household, $account, $category, now()->toDateString(), 'DiaA');
        foreach (range(1, 20) as $n) {
            $this->expenseOn($owner, $household, $account, $category, $ayer, "DiaB-{$n}");
        }
        foreach (range(1, 4) as $n) {
            $this->expenseOn($owner, $household, $account, $category, $anteayer, "DiaC-{$n}");
        }

        // El corte de los 20 caería dentro del día B: la página se extiende
        // para cerrarlo (1 de A + 20 de B = 21) y no mezclar el día en dos
        // pantallas.
        $this->actingAs($owner)->get(route('movements.index'))
            ->assertOk()
            ->assertSee('DiaB-20')
            ->assertDontSee('DiaC-1');

        $this->actingAs($owner)
            ->get(route('movements.index', ['offset' => 21]), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertSee('DiaC-1')
            ->assertDontSee('DiaB-20');
    }

    public function test_el_balance_del_filtro_no_se_limita_a_la_pagina_visible(): void
    {
        [$owner, $household, $account, $category] = $this->setupForPagination();

        // 22 gastos de $10.000 (22 días) + 1 ingreso de $50.000.
        foreach (range(1, 22) as $i) {
            $this->expenseOn($owner, $household, $account, $category, now()->subDays($i)->toDateString(), "Gasto balance {$i}");
        }

        Income::factory()->create([
            'household_id' => $household->id,
            'user_id' => $owner->id,
            'account_id' => $account->id,
            'amount' => 50000,
            'date' => now()->toDateString(),
            'description' => 'Ingreso balance',
        ]);

        // El balance es del filtro completo (50.000 − 220.000), no de los
        // 20 visibles (que dirían 150.000).
        $this->actingAs($owner)->get(route('movements.index'))
            ->assertOk()
            ->assertSee('$ 170.000,00')
            ->assertDontSee('$ 150.000,00');
    }
}
