<?php

namespace Tests\Feature\Household;

use App\Enums\CategoryType;
use App\Enums\Frequency;
use App\Models\Account;
use App\Models\Category;
use App\Models\Expense;
use App\Models\Household;
use App\Models\User;
use App\Services\HouseholdService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Aislamiento cuando un MISMO usuario pertenece a varios hogares (ADR-0011).
 *
 * Es el caso que los tests de aislamiento clásicos no cubren: usan un intruso
 * que no es miembro de nada, así que la Policy lo frena por membresía. Aquí el
 * usuario SÍ es miembro de los dos hogares, y lo que debe frenarlo es que el
 * hogar del recurso no sea su hogar activo.
 *
 * Sin ese segundo requisito, los Form Requests (que acotan account_id al hogar
 * ACTIVO) y las Policies (que miraban el hogar DEL RECURSO) discrepaban, y se
 * podía enlazar una cuenta del hogar A a un recurso del hogar B.
 */
class MultiHouseholdIsolationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Household $hogarA;

    private Household $hogarB;

    private Account $cuentaA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $service = app(HouseholdService::class);

        $this->hogarA = $service->createHousehold($this->user->id, 'Hogar A');
        $this->hogarB = $service->createHousehold($this->user->id, 'Hogar B');

        $this->cuentaA = Account::factory()->create([
            'household_id' => $this->hogarA->id,
            'name' => 'CuentaSecretaDeA',
        ]);
    }

    /**
     * Actúa con el hogar A como activo.
     */
    private function actingWithHouseholdA(): self
    {
        $this->withSession(['household_id' => $this->hogarA->id])->actingAs($this->user);

        return $this;
    }

    // ===== El fallo original: enlazar una cuenta de A a un recurso de B =====

    public function test_no_puede_enlazar_cuenta_del_hogar_activo_a_un_recurrente_de_otro_hogar(): void
    {
        $recurrenteB = $this->hogarB->recurringExpenses()->create([
            'name' => 'Arriendo B',
            'amount' => 500000,
            'frequency' => Frequency::Monthly->value,
            'next_date' => Carbon::now()->addDays(5)->toDateString(),
            'is_active' => true,
        ]);

        $this->actingWithHouseholdA()
            ->put(route('recurring-expenses.update', $recurrenteB), [
                'name' => 'Arriendo B',
                'amount' => 500000,
                'frequency' => Frequency::Monthly->value,
                'next_date' => Carbon::now()->addDays(5)->toDateString(),
                'account_id' => $this->cuentaA->id,
                'is_active' => '1',
            ])
            ->assertForbidden();

        $this->assertNull($recurrenteB->fresh()->account_id);
    }

    public function test_no_puede_marcar_pagado_un_recurrente_de_otro_hogar(): void
    {
        $recurrenteB = $this->hogarB->recurringExpenses()->create([
            'name' => 'Arriendo B',
            'amount' => 500000,
            'frequency' => Frequency::Monthly->value,
            'next_date' => Carbon::now()->addDays(5)->toDateString(),
            'is_active' => true,
        ]);

        $this->actingWithHouseholdA()
            ->post(route('recurring-expenses.mark-paid', $recurrenteB))
            ->assertForbidden();

        $this->assertSame(0, Expense::count());
    }

    public function test_no_puede_enlazar_cuenta_del_hogar_activo_a_un_gasto_de_otro_hogar(): void
    {
        $cuentaB = Account::factory()->create(['household_id' => $this->hogarB->id]);
        $gastoB = Expense::factory()->create([
            'household_id' => $this->hogarB->id,
            'user_id' => $this->user->id,
            'account_id' => $cuentaB->id,
            'amount' => 1000,
            'date' => Carbon::now()->toDateString(),
        ]);

        $this->actingWithHouseholdA()
            ->put(route('expenses.update', $gastoB), [
                'amount' => 1000,
                'account_id' => $this->cuentaA->id,
                'date' => Carbon::now()->toDateString(),
            ])
            ->assertForbidden();

        $this->assertSame($cuentaB->id, $gastoB->fresh()->account_id);
    }

    // ===== El invariante, recurso por recurso =====

    public function test_no_puede_editar_una_cuenta_de_otro_hogar_propio(): void
    {
        $cuentaB = Account::factory()->create(['household_id' => $this->hogarB->id, 'name' => 'Original']);

        $this->actingWithHouseholdA()
            ->put(route('accounts.update', $cuentaB), [
                'name' => 'Renombrada desde A',
                'type' => 'cash',
                'initial_balance' => 0,
                'currency' => 'COP',
            ])
            ->assertForbidden();

        $this->assertSame('Original', $cuentaB->fresh()->name);
    }

    public function test_no_puede_editar_un_presupuesto_de_otro_hogar_propio(): void
    {
        $now = Carbon::now(config('app.timezone'));
        $presupuestoB = $this->hogarB->budgets()->create([
            'category_id' => null,
            'amount' => 1000000,
            'period' => 'monthly',
            'year' => $now->year,
            'month' => $now->month,
        ]);

        $this->actingWithHouseholdA()
            ->put(route('budgets.update', $presupuestoB), ['amount' => 9999999])
            ->assertForbidden();

        $this->assertSame('1000000.00', (string) $presupuestoB->fresh()->amount);
    }

    public function test_no_puede_editar_un_ingreso_esperado_de_otro_hogar_propio(): void
    {
        $ingresoB = $this->hogarB->expectedIncomes()->create([
            'name' => 'Salario B',
            'amount' => 3000000,
        ]);

        $this->actingWithHouseholdA()
            ->put(route('expected-incomes.update', $ingresoB), [
                'name' => 'Hackeado',
                'amount' => 1,
            ])
            ->assertForbidden();

        $this->assertSame('Salario B', $ingresoB->fresh()->name);
    }

    public function test_no_puede_editar_una_categoria_de_otro_hogar_propio(): void
    {
        $categoriaB = Category::create([
            'household_id' => $this->hogarB->id,
            'name' => 'Categoría B',
            'type' => CategoryType::Expense->value,
            'is_default' => false,
        ]);

        $this->actingWithHouseholdA()
            ->put(route('categories.update', $categoriaB), ['name' => 'Hackeada'])
            ->assertForbidden();

        $this->assertSame('Categoría B', $categoriaB->fresh()->name);
    }

    // ===== Y sigue funcionando al cambiar de hogar activo =====

    public function test_al_activar_el_otro_hogar_si_puede_operar_sobre_sus_recursos(): void
    {
        $cuentaB = Account::factory()->create(['household_id' => $this->hogarB->id, 'name' => 'Original']);

        $this->withSession(['household_id' => $this->hogarB->id])
            ->actingAs($this->user)
            ->put(route('accounts.update', $cuentaB), [
                'name' => 'Renombrada con B activo',
                'type' => 'cash',
                'initial_balance' => 0,
                'currency' => 'COP',
            ])
            ->assertRedirect(route('accounts.index'));

        $this->assertSame('Renombrada con B activo', $cuentaB->fresh()->name);
    }

    public function test_el_selector_de_hogar_sigue_permitiendo_gestionar_un_hogar_no_activo(): void
    {
        // Gestionar hogares (ver, renombrar, activar) NO se acota al hogar
        // activo: si no, sería imposible cambiar de hogar.
        $this->actingWithHouseholdA()
            ->get(route('households.show', $this->hogarB))
            ->assertOk();

        $this->actingWithHouseholdA()
            ->post(route('households.activate', $this->hogarB))
            ->assertRedirect();
    }
}
