<?php

namespace Tests\Feature\Recurring;

use App\Enums\CategoryType;
use App\Enums\Frequency;
use App\Models\Account;
use App\Models\Category;
use App\Models\Household;
use App\Models\RecurringExpense;
use App\Models\User;
use App\Services\HouseholdService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecurringExpenseTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: Household}
     */
    private function setupHousehold(string $name = 'Hogar A'): array
    {
        $owner = User::factory()->create();
        $household = app(HouseholdService::class)->createHousehold($owner->id, $name);

        return [$owner, $household];
    }

    // ===== Acceso y CRUD =====

    public function test_guest_es_redirigido_al_login(): void
    {
        $this->get(route('recurring-expenses.index'))->assertRedirect(route('login'));
    }

    public function test_usuario_ve_sus_obligaciones_y_el_ahorro_recomendado(): void
    {
        [$owner, $household] = $this->setupHousehold();
        $household->recurringExpenses()->create([
            'name' => 'SOAT', 'amount' => 600000,
            'frequency' => Frequency::Yearly->value,
            'next_date' => now()->addDays(40)->toDateString(),
        ]);

        $this->actingAs($owner)
            ->get(route('recurring-expenses.index'))
            ->assertOk()
            ->assertSee('SOAT')
            ->assertSee('50.000,00'); // ahorro mensual recomendado en COP
    }

    public function test_usuario_puede_crear_un_gasto_recurrente(): void
    {
        [$owner, $household] = $this->setupHousehold();

        $this->actingAs($owner)->post(route('recurring-expenses.store'), [
            'name' => 'Arriendo',
            'amount' => 1200000,
            'frequency' => 'monthly',
            'next_date' => '2026-09-05',
            'is_active' => '1',
        ])->assertRedirect(route('recurring-expenses.index'));

        $this->assertDatabaseHas('recurring_expenses', [
            'household_id' => $household->id,
            'name' => 'Arriendo',
            'amount' => '1200000.00',
            'frequency' => 'monthly',
            'next_date' => '2026-09-05',
            'is_active' => true,
        ]);
    }

    public function test_usuario_puede_actualizar_un_gasto_recurrente(): void
    {
        [$owner, $household] = $this->setupHousehold();
        $item = $household->recurringExpenses()->create([
            'name' => 'SOAT', 'amount' => 600000,
            'frequency' => Frequency::Yearly->value, 'next_date' => '2026-11-15',
        ]);

        $this->actingAs($owner)->put(route('recurring-expenses.update', $item), [
            'name' => 'SOAT carro',
            'amount' => 650000,
            'frequency' => 'yearly',
            'next_date' => '2026-11-20',
        ])->assertRedirect(route('recurring-expenses.index'));

        $fresh = $item->fresh();
        $this->assertSame('SOAT carro', $fresh->name);
        $this->assertSame('650000.00', (string) $fresh->amount);
        // El checkbox sin marcar debe desactivarlo, no ignorarse.
        $this->assertFalse($fresh->is_active);
    }

    public function test_usuario_puede_eliminar_un_gasto_recurrente(): void
    {
        [$owner, $household] = $this->setupHousehold();
        $item = $household->recurringExpenses()->create([
            'name' => 'SOAT', 'amount' => 600000,
            'frequency' => Frequency::Yearly->value, 'next_date' => '2026-11-15',
        ]);

        $this->actingAs($owner)
            ->delete(route('recurring-expenses.destroy', $item))
            ->assertRedirect(route('recurring-expenses.index'));

        $this->assertDatabaseMissing('recurring_expenses', ['id' => $item->id]);
    }

    // ===== Validación =====

    public function test_campos_obligatorios_y_frecuencia_valida(): void
    {
        [$owner] = $this->setupHousehold();

        $this->actingAs($owner)
            ->post(route('recurring-expenses.store'), [
                'name' => '', 'amount' => '', 'frequency' => 'cuando-pueda', 'next_date' => '',
            ])
            ->assertSessionHasErrors(['name', 'amount', 'frequency', 'next_date']);
    }

    public function test_personalizada_exige_intervalo(): void
    {
        [$owner] = $this->setupHousehold();

        $this->actingAs($owner)->post(route('recurring-expenses.store'), [
            'name' => 'Mantenimiento',
            'amount' => 100000,
            'frequency' => 'custom',
            'next_date' => '2026-09-01',
        ])->assertSessionHasErrors('frequency_interval');
    }

    public function test_el_intervalo_de_otra_frecuencia_se_descarta(): void
    {
        [$owner, $household] = $this->setupHousehold();

        $this->actingAs($owner)->post(route('recurring-expenses.store'), [
            'name' => 'Arriendo',
            'amount' => 100000,
            'frequency' => 'monthly',
            'frequency_interval' => 45, // debe ignorarse (no es custom)
            'next_date' => '2026-09-01',
        ]);

        $this->assertDatabaseHas('recurring_expenses', [
            'household_id' => $household->id,
            'frequency' => 'monthly',
            'frequency_interval' => null,
        ]);
    }

    public function test_no_se_acepta_una_categoria_de_ingreso(): void
    {
        [$owner, $household] = $this->setupHousehold();
        $ingreso = Category::create([
            'household_id' => $household->id,
            'name' => 'Salario',
            'type' => CategoryType::Income->value,
            'is_default' => false,
        ]);

        $this->actingAs($owner)->post(route('recurring-expenses.store'), [
            'name' => 'Arriendo',
            'amount' => 100000,
            'frequency' => 'monthly',
            'next_date' => '2026-09-01',
            'category_id' => $ingreso->id,
        ])->assertSessionHasErrors('category_id');
    }

    public function test_no_se_acepta_una_cuenta_de_otro_hogar(): void
    {
        [$owner] = $this->setupHousehold();
        [, $otro] = $this->setupHousehold('Hogar B');
        $cuentaAjena = Account::factory()->create(['household_id' => $otro->id]);

        $this->actingAs($owner)->post(route('recurring-expenses.store'), [
            'name' => 'Arriendo',
            'amount' => 100000,
            'frequency' => 'monthly',
            'next_date' => '2026-09-01',
            'account_id' => $cuentaAjena->id,
        ])->assertSessionHasErrors('account_id');
    }

    public function test_no_se_puede_forzar_el_household_id_por_mass_assignment(): void
    {
        [$owner, $household] = $this->setupHousehold();
        [, $otro] = $this->setupHousehold('Hogar B');

        $this->actingAs($owner)->post(route('recurring-expenses.store'), [
            'household_id' => $otro->id, // intento de inyección
            'name' => 'Arriendo',
            'amount' => 100000,
            'frequency' => 'monthly',
            'next_date' => '2026-09-01',
        ]);

        $this->assertDatabaseHas('recurring_expenses', ['household_id' => $household->id]);
        $this->assertDatabaseMissing('recurring_expenses', ['household_id' => $otro->id]);
    }

    // ===== Marcar pagado =====

    public function test_marcar_pagado_registra_el_gasto_y_avanza_la_fecha(): void
    {
        [$owner, $household] = $this->setupHousehold();
        $account = Account::factory()->create([
            'household_id' => $household->id,
            'initial_balance' => 2000000,
            'current_balance' => 2000000,
        ]);
        $item = $household->recurringExpenses()->create([
            'name' => 'Arriendo', 'amount' => 1200000,
            'frequency' => Frequency::Monthly->value,
            'next_date' => now()->startOfMonth()->addDays(4)->toDateString(), // día 5 de este mes
            'account_id' => $account->id,
        ]);

        $this->actingAs($owner)
            ->post(route('recurring-expenses.mark-paid', $item))
            ->assertRedirect(route('recurring-expenses.index'));

        $this->assertDatabaseCount('expenses', 1);
        $this->assertDatabaseHas('expenses', [
            'household_id' => $household->id,
            'account_id' => $account->id,
            'amount' => '1200000.00',
            'description' => 'Arriendo (pago recurrente)',
        ]);
        // Mensual: avanza un mes sin desbordar.
        $this->assertSame(
            $item->next_date->copy()->addMonthNoOverflow()->toDateString(),
            $item->fresh()->next_date->toDateString(),
        );
        // Saldo de la cuenta recomputado.
        $this->assertSame('800000.00', (string) $account->fresh()->current_balance);
    }

    public function test_marcar_pagado_sin_cuenta_no_registra_gasto(): void
    {
        [$owner, $household] = $this->setupHousehold();
        $item = $household->recurringExpenses()->create([
            'name' => 'SOAT', 'amount' => 600000,
            'frequency' => Frequency::Yearly->value, 'next_date' => '2026-11-15',
        ]);

        $this->actingAs($owner)
            ->post(route('recurring-expenses.mark-paid', $item))
            ->assertRedirect(route('recurring-expenses.index'));

        $this->assertDatabaseCount('expenses', 0);
        $this->assertSame('2027-11-15', $item->fresh()->next_date->toDateString());
    }

    // ===== Aislamiento multi-hogar (amenaza #1 — IDOR) =====

    public function test_usuario_ajeno_no_puede_editar_recurrente_de_otro_hogar(): void
    {
        [, $household] = $this->setupHousehold();
        $item = $household->recurringExpenses()->create([
            'name' => 'SecretoSOAT', 'amount' => 600000,
            'frequency' => Frequency::Yearly->value, 'next_date' => '2026-11-15',
        ]);
        [$intruso] = $this->setupHousehold('Hogar Intruso');

        $this->actingAs($intruso)->put(route('recurring-expenses.update', $item), [
            'name' => 'Hack',
            'amount' => 1,
            'frequency' => 'monthly',
            'next_date' => '2026-09-01',
        ])->assertForbidden();

        $this->assertSame('SecretoSOAT', $item->fresh()->name);
    }

    public function test_usuario_ajeno_no_puede_eliminar_recurrente_de_otro_hogar(): void
    {
        [, $household] = $this->setupHousehold();
        $item = $household->recurringExpenses()->create([
            'name' => 'SecretoSOAT', 'amount' => 600000,
            'frequency' => Frequency::Yearly->value, 'next_date' => '2026-11-15',
        ]);
        [$intruso] = $this->setupHousehold('Hogar Intruso');

        $this->actingAs($intruso)
            ->delete(route('recurring-expenses.destroy', $item))
            ->assertForbidden();

        $this->assertDatabaseHas('recurring_expenses', ['id' => $item->id]);
    }

    public function test_usuario_ajeno_no_puede_marcar_pagado_un_recurrente_de_otro_hogar(): void
    {
        [, $household] = $this->setupHousehold();
        $item = $household->recurringExpenses()->create([
            'name' => 'SecretoSOAT', 'amount' => 600000,
            'frequency' => Frequency::Yearly->value, 'next_date' => '2026-11-15',
        ]);
        [$intruso] = $this->setupHousehold('Hogar Intruso');

        $this->actingAs($intruso)
            ->post(route('recurring-expenses.mark-paid', $item))
            ->assertForbidden();

        $this->assertDatabaseCount('expenses', 0);
        $this->assertSame('2026-11-15', $item->fresh()->next_date->toDateString());
    }

    public function test_el_listado_no_muestra_recurrentes_de_otro_hogar(): void
    {
        [, $household] = $this->setupHousehold();
        $household->recurringExpenses()->create([
            'name' => 'SecretoSOAT', 'amount' => 600000,
            'frequency' => Frequency::Yearly->value, 'next_date' => '2026-11-15',
        ]);
        [$intruso] = $this->setupHousehold('Hogar Intruso');

        $this->actingAs($intruso)
            ->get(route('recurring-expenses.index'))
            ->assertOk()
            ->assertDontSee('SecretoSOAT');
    }

    public function test_el_panel_muestra_alertas_de_obligaciones_proximas(): void
    {
        [$owner, $household] = $this->setupHousehold();
        $household->recurringExpenses()->create([
            'name' => 'SOAT muy cerca', 'amount' => 600000,
            'frequency' => Frequency::Yearly->value,
            'next_date' => now()->addDays(20)->toDateString(),
        ]);

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('SOAT muy cerca vence en 20 días');
    }

    public function test_los_inactivos_no_generan_avisos_en_el_panel(): void
    {
        [$owner, $household] = $this->setupHousehold();
        RecurringExpense::factory()->inactive()->create([
            'household_id' => $household->id,
            'name' => 'PausadoAlerta',
            'next_date' => now()->addDays(3)->toDateString(),
        ]);

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('PausadoAlerta');
    }
}
