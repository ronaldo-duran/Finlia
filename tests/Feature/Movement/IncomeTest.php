<?php

namespace Tests\Feature\Movement;

use App\Enums\CategoryType;
use App\Models\Account;
use App\Models\Category;
use App\Models\Household;
use App\Models\Income;
use App\Models\User;
use App\Services\HouseholdService;
use App\Services\MovementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IncomeTest extends TestCase
{
    use RefreshDatabase;

    private function setupWithAccount(): array
    {
        $owner = User::factory()->create();
        $household = app(HouseholdService::class)->createHousehold($owner->id, 'Hogar A');
        $account = Account::factory()->withInitialBalance(100000)->create(['household_id' => $household->id]);
        $category = Category::create([
            'name' => 'Salario', 'type' => CategoryType::Income->value,
            'household_id' => null, 'is_default' => true, 'color' => '#16a34a',
        ]);

        return [$owner, $household, $account, $category];
    }

    public function test_guest_es_redirigido_al_login(): void
    {
        $this->get(route('incomes.create'))->assertRedirect(route('login'));
    }

    public function test_usuario_puede_registrar_un_ingreso_y_el_saldo_sube(): void
    {
        [$owner, , $account, $category] = $this->setupWithAccount();

        $this->actingAs($owner)->post(route('incomes.store'), [
            'amount' => 500000,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'date' => now()->format('Y-m-d'),
            'source' => 'Salario',
        ])->assertRedirect(route('dashboard'));

        $income = Income::first();
        $this->assertSame('500000.00', (string) $income->amount);
        // Saldo = inicial + ingreso (ADR-0012).
        $this->assertSame('600000.00', (string) $account->fresh()->current_balance);
    }

    public function test_valida_campos_obligatorios(): void
    {
        [$owner, , $account] = $this->setupWithAccount();

        $this->actingAs($owner)->post(route('incomes.store'), [
            'amount' => '',
            'account_id' => $account->id,
            'date' => now()->format('Y-m-d'),
        ])->assertSessionHasErrors('amount');
    }

    public function test_rechaza_categoria_de_gasto_en_un_ingreso(): void
    {
        [$owner, , $account] = $this->setupWithAccount();
        $expenseCat = Category::create([
            'name' => 'Transporte', 'type' => CategoryType::Expense->value,
            'household_id' => null, 'is_default' => true,
        ]);

        $this->actingAs($owner)->post(route('incomes.store'), [
            'amount' => 1000,
            'account_id' => $account->id,
            'category_id' => $expenseCat->id,
            'date' => now()->format('Y-m-d'),
        ])->assertSessionHasErrors('category_id');
    }

    // ===== Aislamiento multi-hogar (amenaza #1 — IDOR) =====

    public function test_usuario_ajeno_no_puede_editar_ingreso_de_otro_hogar(): void
    {
        [$owner, $household, $account, $category] = $this->setupWithAccount();

        $income = app(MovementService::class)->createIncome(
            household: Household::find($household->id),
            user: $owner,
            data: ['amount' => 1000, 'account_id' => $account->id, 'category_id' => $category->id, 'date' => now()->format('Y-m-d')],
        );

        $intruder = User::factory()->create();
        app(HouseholdService::class)->createHousehold($intruder->id, 'Hogar Intruso');

        $this->actingAs($intruder)
            ->get(route('incomes.edit', $income))
            ->assertForbidden();

        $this->actingAs($intruder)->put(route('incomes.update', $income), [
            'amount' => 1, 'account_id' => $account->id, 'date' => now()->format('Y-m-d'),
        ])->assertForbidden();

        $this->actingAs($intruder)
            ->delete(route('incomes.destroy', $income))
            ->assertForbidden();
    }
}
