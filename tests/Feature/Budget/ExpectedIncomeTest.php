<?php

namespace Tests\Feature\Budget;

use App\Enums\CategoryType;
use App\Models\Category;
use App\Models\ExpectedIncome;
use App\Models\Household;
use App\Models\User;
use App\Services\HouseholdService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpectedIncomeTest extends TestCase
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
        $this->get(route('expected-incomes.index'))->assertRedirect(route('login'));
    }

    public function test_usuario_ve_sus_ingresos_esperados_y_el_total(): void
    {
        [$owner, $household] = $this->setupHousehold();
        $household->expectedIncomes()->create(['name' => 'Salario', 'amount' => 3000000]);
        $household->expectedIncomes()->create(['name' => 'Arriendo', 'amount' => 900000]);

        $this->actingAs($owner)
            ->get(route('expected-incomes.index'))
            ->assertOk()
            ->assertSee('Salario')
            ->assertSee('Arriendo')
            ->assertSee('3.900.000,00'); // total mensual formateado en COP
    }

    public function test_usuario_puede_crear_un_ingreso_esperado(): void
    {
        [$owner, $household] = $this->setupHousehold();

        $this->actingAs($owner)->post(route('expected-incomes.store'), [
            'name' => 'Salario',
            'amount' => 3500000,
            'day_of_month' => 30,
            'is_active' => '1',
        ])->assertRedirect(route('expected-incomes.index'));

        $this->assertDatabaseHas('expected_incomes', [
            'household_id' => $household->id,
            'name' => 'Salario',
            'amount' => '3500000.00',
            'day_of_month' => 30,
            'is_active' => true,
        ]);
    }

    public function test_usuario_puede_actualizar_un_ingreso_esperado(): void
    {
        [$owner, $household] = $this->setupHousehold();
        $item = $household->expectedIncomes()->create(['name' => 'Salario', 'amount' => 3000000]);

        $this->actingAs($owner)->put(route('expected-incomes.update', $item), [
            'name' => 'Salario nuevo',
            'amount' => 4000000,
        ])->assertRedirect(route('expected-incomes.index'));

        $fresh = $item->fresh();
        $this->assertSame('Salario nuevo', $fresh->name);
        $this->assertSame('4000000.00', (string) $fresh->amount);
        // El checkbox sin marcar debe desactivarlo, no ignorarse.
        $this->assertFalse($fresh->is_active);
    }

    public function test_usuario_puede_eliminar_un_ingreso_esperado(): void
    {
        [$owner, $household] = $this->setupHousehold();
        $item = $household->expectedIncomes()->create(['name' => 'Salario', 'amount' => 3000000]);

        $this->actingAs($owner)
            ->delete(route('expected-incomes.destroy', $item))
            ->assertRedirect(route('expected-incomes.index'));

        $this->assertDatabaseMissing('expected_incomes', ['id' => $item->id]);
    }

    // ===== Validación =====

    public function test_nombre_y_monto_son_obligatorios(): void
    {
        [$owner] = $this->setupHousehold();

        $this->actingAs($owner)
            ->post(route('expected-incomes.store'), ['name' => '', 'amount' => ''])
            ->assertSessionHasErrors(['name', 'amount']);
    }

    public function test_el_dia_de_cobro_debe_estar_entre_1_y_31(): void
    {
        [$owner] = $this->setupHousehold();

        $this->actingAs($owner)->post(route('expected-incomes.store'), [
            'name' => 'Salario',
            'amount' => 100000,
            'day_of_month' => 45,
        ])->assertSessionHasErrors('day_of_month');
    }

    public function test_no_se_acepta_una_categoria_de_gasto(): void
    {
        [$owner, $household] = $this->setupHousehold();
        $gasto = Category::create([
            'household_id' => $household->id,
            'name' => 'Transporte',
            'type' => CategoryType::Expense->value,
            'is_default' => false,
        ]);

        $this->actingAs($owner)->post(route('expected-incomes.store'), [
            'name' => 'Salario',
            'amount' => 100000,
            'category_id' => $gasto->id,
        ])->assertSessionHasErrors('category_id');
    }

    public function test_no_se_puede_forzar_el_household_id_por_mass_assignment(): void
    {
        [$owner, $household] = $this->setupHousehold();
        [, $otro] = $this->setupHousehold('Hogar B');

        $this->actingAs($owner)->post(route('expected-incomes.store'), [
            'household_id' => $otro->id, // intento de inyección
            'name' => 'Salario',
            'amount' => 100000,
        ]);

        $this->assertDatabaseHas('expected_incomes', ['household_id' => $household->id]);
        $this->assertDatabaseMissing('expected_incomes', ['household_id' => $otro->id]);
    }

    // ===== Aislamiento multi-hogar (amenaza #1 — IDOR) =====

    public function test_usuario_ajeno_no_puede_editar_ingreso_de_otro_hogar(): void
    {
        [, $household] = $this->setupHousehold();
        $item = $household->expectedIncomes()->create(['name' => 'Salario', 'amount' => 3000000]);
        [$intruso] = $this->setupHousehold('Hogar Intruso');

        $this->actingAs($intruso)->put(route('expected-incomes.update', $item), [
            'name' => 'Hack',
            'amount' => 1,
        ])->assertForbidden();

        $this->assertSame('Salario', $item->fresh()->name);
    }

    public function test_usuario_ajeno_no_puede_eliminar_ingreso_de_otro_hogar(): void
    {
        [, $household] = $this->setupHousehold();
        $item = $household->expectedIncomes()->create(['name' => 'Salario', 'amount' => 3000000]);
        [$intruso] = $this->setupHousehold('Hogar Intruso');

        $this->actingAs($intruso)
            ->delete(route('expected-incomes.destroy', $item))
            ->assertForbidden();

        $this->assertDatabaseHas('expected_incomes', ['id' => $item->id]);
    }

    public function test_el_listado_no_muestra_ingresos_de_otro_hogar(): void
    {
        [, $household] = $this->setupHousehold();
        $household->expectedIncomes()->create(['name' => 'SalarioSecreto', 'amount' => 3000000]);
        [$intruso] = $this->setupHousehold('Hogar Intruso');

        $this->actingAs($intruso)
            ->get(route('expected-incomes.index'))
            ->assertOk()
            ->assertDontSee('SalarioSecreto');
    }

    public function test_el_total_mensual_solo_cuenta_los_activos(): void
    {
        [$owner, $household] = $this->setupHousehold();
        $household->expectedIncomes()->create(['name' => 'Salario', 'amount' => 3000000]);
        ExpectedIncome::factory()->inactive()->create([
            'household_id' => $household->id,
            'name' => 'Bono pausado',
            'amount' => 1000000,
        ]);

        $this->actingAs($owner)
            ->get(route('expected-incomes.index'))
            ->assertOk()
            ->assertSee('3.000.000,00');
    }
}
