<?php

namespace Tests\Feature\Movement;

use App\Enums\CategoryType;
use App\Models\Account;
use App\Models\Category;
use App\Models\Expense;
use App\Models\Household;
use App\Models\User;
use App\Services\HouseholdService;
use App\Services\MovementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpenseTest extends TestCase
{
    use RefreshDatabase;

    private function setupWithAccount(): array
    {
        $owner = User::factory()->create();
        $household = app(HouseholdService::class)->createHousehold($owner->id, 'Hogar A');
        $account = Account::factory()->withInitialBalance(100000)->create(['household_id' => $household->id]);
        $category = Category::create([
            'name' => 'Alimentación', 'type' => CategoryType::Expense->value,
            'household_id' => null, 'is_default' => true, 'color' => '#ef4444',
        ]);

        return [$owner, $household, $account, $category];
    }

    public function test_guest_es_redirigido_al_login(): void
    {
        $this->get(route('expenses.create'))->assertRedirect(route('login'));
    }

    public function test_usuario_puede_registrar_un_gasto_y_el_saldo_baja(): void
    {
        [$owner, , $account, $category] = $this->setupWithAccount();

        $this->actingAs($owner)->post(route('expenses.store'), [
            'amount' => 30000,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'date' => now()->format('Y-m-d'),
            'description' => 'Mercado',
        ])->assertRedirect(route('dashboard'));

        $expense = Expense::first();
        $this->assertSame('30000.00', (string) $expense->amount);
        // Saldo = inicial − gasto (ADR-0012).
        $this->assertSame('70000.00', (string) $account->fresh()->current_balance);
    }

    public function test_valida_campos_obligatorios(): void
    {
        [$owner, , $account] = $this->setupWithAccount();

        $this->actingAs($owner)->post(route('expenses.store'), [
            'amount' => '',
            'account_id' => $account->id,
            'date' => now()->format('Y-m-d'),
        ])->assertSessionHasErrors('amount');
    }

    public function test_rechaza_cuenta_de_otro_hogar(): void
    {
        [$owner] = $this->setupWithAccount();

        $otro = User::factory()->create();
        $otroHogar = app(HouseholdService::class)->createHousehold($otro->id, 'Hogar B');
        $cuentaAjena = Account::factory()->create(['household_id' => $otroHogar->id]);

        $this->actingAs($owner)->post(route('expenses.store'), [
            'amount' => 1000,
            'account_id' => $cuentaAjena->id,
            'date' => now()->format('Y-m-d'),
        ])->assertSessionHasErrors('account_id');
    }

    public function test_editar_un_gasto_recomputa_el_saldo(): void
    {
        [$owner, , $account, $category] = $this->setupWithAccount();

        $expense = app(MovementService::class)->createExpense(
            household: app(Household::class)->find($account->household_id),
            user: $owner,
            data: [
                'amount' => 30000,
                'account_id' => $account->id,
                'category_id' => $category->id,
                'date' => now()->format('Y-m-d'),
            ],
        );
        $this->assertSame('70000.00', (string) $account->fresh()->current_balance);

        // Subir el gasto a 60000 → saldo baja a 40000.
        $this->actingAs($owner)->put(route('expenses.update', $expense), [
            'amount' => 60000,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'date' => now()->format('Y-m-d'),
        ])->assertRedirect(route('movements.index'));

        $this->assertSame('40000.00', (string) $account->fresh()->current_balance);
    }

    public function test_borrar_un_gasto_recomputa_el_saldo(): void
    {
        [$owner, , $account, $category] = $this->setupWithAccount();

        $service = app(MovementService::class);
        $household = Household::find($account->household_id);
        $expense = $service->createExpense(
            household: $household, user: $owner,
            data: ['amount' => 25000, 'account_id' => $account->id, 'category_id' => $category->id, 'date' => now()->format('Y-m-d')],
        );
        $this->assertSame('75000.00', (string) $account->fresh()->current_balance);

        $this->actingAs($owner)
            ->delete(route('expenses.destroy', $expense))
            ->assertRedirect(route('movements.index'));

        // Al borrarse (soft-delete), deja de restar del saldo.
        $this->assertSame('100000.00', (string) $account->fresh()->current_balance);
        $this->assertSoftDeleted('expenses', ['id' => $expense->id]);
    }

    // ===== Aislamiento multi-hogar (amenaza #1 — IDOR) =====

    public function test_usuario_ajeno_no_puede_editar_gasto_de_otro_hogar(): void
    {
        [$owner, , $account, $category] = $this->setupWithAccount();
        $expense = app(MovementService::class)->createExpense(
            household: Household::find($account->household_id), user: $owner,
            data: ['amount' => 1000, 'account_id' => $account->id, 'category_id' => $category->id, 'date' => now()->format('Y-m-d')],
        );

        $intruder = User::factory()->create();
        app(HouseholdService::class)->createHousehold($intruder->id, 'Hogar Intruso');

        $this->actingAs($intruder)
            ->get(route('expenses.edit', $expense))
            ->assertForbidden();

        $this->actingAs($intruder)->put(route('expenses.update', $expense), [
            'amount' => 1, 'account_id' => $account->id, 'date' => now()->format('Y-m-d'),
        ])->assertForbidden();

        $this->actingAs($intruder)
            ->delete(route('expenses.destroy', $expense))
            ->assertForbidden();
    }
}
