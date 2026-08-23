<?php

namespace Tests\Feature\Budget;

use App\Enums\CategoryType;
use App\Models\Account;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Expense;
use App\Models\Household;
use App\Models\User;
use App\Services\HouseholdService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BudgetTest extends TestCase
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

    // ===== Acceso =====

    public function test_guest_es_redirigido_al_login(): void
    {
        $this->get(route('budgets.index'))->assertRedirect(route('login'));
    }

    public function test_usuario_ve_el_panel_de_presupuestos(): void
    {
        [$owner] = $this->setupHousehold();

        $this->actingAs($owner)
            ->get(route('budgets.index'))
            ->assertOk()
            ->assertSee('Puedes gastar aproximadamente');
    }

    public function test_los_tres_periodos_responden_ok(): void
    {
        [$owner] = $this->setupHousehold();

        foreach (['semana', 'mes', 'proximo-mes'] as $periodo) {
            $this->actingAs($owner)
                ->get(route('budgets.index', ['periodo' => $periodo]))
                ->assertOk();
        }
    }

    public function test_un_periodo_invalido_cae_en_el_mes_actual(): void
    {
        [$owner] = $this->setupHousehold();

        $this->actingAs($owner)
            ->get(route('budgets.index', ['periodo' => 'no-existe']))
            ->assertOk()
            ->assertSee('Este mes');
    }

    public function test_el_formulario_de_alta_se_renderiza(): void
    {
        [$owner, $household] = $this->setupHousehold();
        $this->expenseCategory($household, 'Alimentación');

        $this->actingAs($owner)
            ->get(route('budgets.create'))
            ->assertOk()
            ->assertSee('Nuevo presupuesto')
            ->assertSee('Alimentación');
    }

    public function test_el_formulario_de_alta_precarga_el_proximo_mes(): void
    {
        [$owner] = $this->setupHousehold();
        $nextMonth = Carbon::now(config('app.timezone'))->addMonthNoOverflow();

        $this->actingAs($owner)
            ->get(route('budgets.create', ['periodo' => 'proximo-mes']))
            ->assertOk()
            ->assertSee('value="'.$nextMonth->year.'"', escape: false);
    }

    public function test_el_formulario_de_edicion_se_renderiza(): void
    {
        [$owner, $household] = $this->setupHousehold();
        $budget = $this->budgetFor($household, 1234500);

        $this->actingAs($owner)
            ->get(route('budgets.edit', $budget))
            ->assertOk()
            ->assertSee('Editar presupuesto')
            ->assertSee('1234500.00');
    }

    // ===== CRUD =====

    public function test_usuario_puede_crear_un_presupuesto_total(): void
    {
        [$owner, $household] = $this->setupHousehold();
        $now = Carbon::now(config('app.timezone'));

        $this->actingAs($owner)->post(route('budgets.store'), [
            'category_id' => '',
            'amount' => 2000000,
            'year' => $now->year,
            'month' => $now->month,
        ])->assertRedirect(route('budgets.index'));

        $this->assertDatabaseHas('budgets', [
            'household_id' => $household->id,
            'category_id' => null,
            'amount' => '2000000.00',
            'period' => 'monthly',
        ]);
    }

    public function test_usuario_puede_crear_un_presupuesto_por_categoria(): void
    {
        [$owner, $household] = $this->setupHousehold();
        $categoria = $this->expenseCategory($household);
        $now = Carbon::now(config('app.timezone'));

        $this->actingAs($owner)->post(route('budgets.store'), [
            'category_id' => $categoria->id,
            'amount' => 800000,
            'year' => $now->year,
            'month' => $now->month,
        ])->assertRedirect(route('budgets.index'));

        $this->assertDatabaseHas('budgets', [
            'household_id' => $household->id,
            'category_id' => $categoria->id,
            'amount' => '800000.00',
        ]);
    }

    public function test_el_listado_muestra_el_consumo_de_la_categoria(): void
    {
        [$owner, $household] = $this->setupHousehold();
        $categoria = $this->expenseCategory($household, 'Alimentación');
        $cuenta = Account::factory()->create(['household_id' => $household->id]);
        $now = Carbon::now(config('app.timezone'));

        $household->budgets()->create([
            'category_id' => $categoria->id, 'amount' => 100000,
            'period' => 'monthly', 'year' => $now->year, 'month' => $now->month,
        ]);
        Expense::factory()->create([
            'household_id' => $household->id,
            'user_id' => $owner->id,
            'account_id' => $cuenta->id,
            'category_id' => $categoria->id,
            'amount' => 90000,
            'date' => $now->format('Y-m-d'),
        ]);

        $this->actingAs($owner)
            ->get(route('budgets.index'))
            ->assertOk()
            ->assertSee('Alimentación')
            ->assertSee('Cerca del límite') // 90 % → alerta del 80 %
            ->assertSee('90 %');            // formato colombiano (coma decimal)
    }

    public function test_los_porcentajes_usan_coma_decimal(): void
    {
        [$owner, $household] = $this->setupHousehold();
        $categoria = $this->expenseCategory($household, 'Alimentación');
        $cuenta = Account::factory()->create(['household_id' => $household->id]);
        $now = Carbon::now(config('app.timezone'));

        $household->budgets()->create([
            'category_id' => $categoria->id, 'amount' => 100000,
            'period' => 'monthly', 'year' => $now->year, 'month' => $now->month,
        ]);
        Expense::factory()->create([
            'household_id' => $household->id,
            'user_id' => $owner->id,
            'account_id' => $cuenta->id,
            'category_id' => $categoria->id,
            'amount' => 33330, // 33,33 %
            'date' => $now->format('Y-m-d'),
        ]);

        $this->actingAs($owner)
            ->get(route('budgets.index'))
            ->assertOk()
            ->assertSee('33,3 %')
            ->assertDontSee('33.3 %');
    }

    public function test_usuario_puede_editar_el_monto(): void
    {
        [$owner, $household] = $this->setupHousehold();
        $budget = $this->budgetFor($household, 500000);

        $this->actingAs($owner)
            ->put(route('budgets.update', $budget), ['amount' => 750000])
            ->assertRedirect(route('budgets.index'));

        $this->assertSame('750000.00', (string) $budget->fresh()->amount);
    }

    public function test_usuario_puede_eliminar_un_presupuesto(): void
    {
        [$owner, $household] = $this->setupHousehold();
        $budget = $this->budgetFor($household);

        $this->actingAs($owner)
            ->delete(route('budgets.destroy', $budget))
            ->assertRedirect(route('budgets.index'));

        $this->assertDatabaseMissing('budgets', ['id' => $budget->id]);
    }

    // ===== Validación =====

    public function test_el_monto_es_obligatorio_y_positivo(): void
    {
        [$owner] = $this->setupHousehold();
        $now = Carbon::now(config('app.timezone'));

        $this->actingAs($owner)->post(route('budgets.store'), [
            'amount' => 0,
            'year' => $now->year,
            'month' => $now->month,
        ])->assertSessionHasErrors('amount');
    }

    public function test_no_se_puede_duplicar_el_presupuesto_total_del_mes(): void
    {
        [$owner, $household] = $this->setupHousehold();
        $budget = $this->budgetFor($household);

        $this->actingAs($owner)->post(route('budgets.store'), [
            'category_id' => '',
            'amount' => 999999,
            'year' => $budget->year,
            'month' => $budget->month,
        ])->assertSessionHasErrors('category_id');

        $this->assertSame(1, Budget::where('household_id', $household->id)->count());
    }

    public function test_no_se_puede_duplicar_el_presupuesto_de_una_categoria(): void
    {
        [$owner, $household] = $this->setupHousehold();
        $categoria = $this->expenseCategory($household);
        $budget = $this->budgetFor($household, 300000, $categoria->id);

        $this->actingAs($owner)->post(route('budgets.store'), [
            'category_id' => $categoria->id,
            'amount' => 400000,
            'year' => $budget->year,
            'month' => $budget->month,
        ])->assertSessionHasErrors('category_id');
    }

    public function test_no_se_acepta_una_categoria_de_otro_hogar(): void
    {
        [$owner] = $this->setupHousehold();
        [, $otro] = $this->setupHousehold('Hogar B');
        $ajena = $this->expenseCategory($otro, 'Ajena');
        $now = Carbon::now(config('app.timezone'));

        $this->actingAs($owner)->post(route('budgets.store'), [
            'category_id' => $ajena->id,
            'amount' => 100000,
            'year' => $now->year,
            'month' => $now->month,
        ])->assertSessionHasErrors('category_id');
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
        $now = Carbon::now(config('app.timezone'));

        $this->actingAs($owner)->post(route('budgets.store'), [
            'category_id' => $ingreso->id,
            'amount' => 100000,
            'year' => $now->year,
            'month' => $now->month,
        ])->assertSessionHasErrors('category_id');
    }

    public function test_no_se_puede_forzar_el_household_id_por_mass_assignment(): void
    {
        [$owner, $household] = $this->setupHousehold();
        [, $otro] = $this->setupHousehold('Hogar B');
        $now = Carbon::now(config('app.timezone'));

        $this->actingAs($owner)->post(route('budgets.store'), [
            'household_id' => $otro->id, // intento de inyección
            'amount' => 100000,
            'year' => $now->year,
            'month' => $now->month,
        ]);

        $this->assertDatabaseHas('budgets', ['household_id' => $household->id]);
        $this->assertDatabaseMissing('budgets', ['household_id' => $otro->id]);
    }

    // ===== Aislamiento multi-hogar (amenaza #1 — IDOR) =====

    public function test_usuario_ajeno_no_puede_editar_presupuesto_de_otro_hogar(): void
    {
        [, $household] = $this->setupHousehold();
        $budget = $this->budgetFor($household);
        [$intruso] = $this->setupHousehold('Hogar Intruso');

        $this->actingAs($intruso)
            ->put(route('budgets.update', $budget), ['amount' => 1])
            ->assertForbidden();
    }

    public function test_usuario_ajeno_no_puede_abrir_el_formulario_de_edicion(): void
    {
        [, $household] = $this->setupHousehold();
        $budget = $this->budgetFor($household);
        [$intruso] = $this->setupHousehold('Hogar Intruso');

        $this->actingAs($intruso)
            ->get(route('budgets.edit', $budget))
            ->assertForbidden();
    }

    public function test_usuario_ajeno_no_puede_eliminar_presupuesto_de_otro_hogar(): void
    {
        [, $household] = $this->setupHousehold();
        $budget = $this->budgetFor($household);
        [$intruso] = $this->setupHousehold('Hogar Intruso');

        $this->actingAs($intruso)
            ->delete(route('budgets.destroy', $budget))
            ->assertForbidden();

        $this->assertDatabaseHas('budgets', ['id' => $budget->id]);
    }

    public function test_el_panel_no_muestra_presupuestos_de_otro_hogar(): void
    {
        [, $household] = $this->setupHousehold();
        $this->budgetFor($household, 4444444, $this->expenseCategory($household, 'Secreta')->id);
        [$intruso] = $this->setupHousehold('Hogar Intruso');

        $this->actingAs($intruso)
            ->get(route('budgets.index'))
            ->assertOk()
            ->assertDontSee('Secreta');
    }

    // ===== Helpers =====

    private function expenseCategory(Household $household, string $name = 'Transporte'): Category
    {
        return Category::create([
            'household_id' => $household->id,
            'name' => $name,
            'type' => CategoryType::Expense->value,
            'is_default' => false,
            'color' => '#0f766e',
        ]);
    }

    private function budgetFor(Household $household, float $amount = 1000000, ?int $categoryId = null): Budget
    {
        $now = Carbon::now(config('app.timezone'));

        return $household->budgets()->create([
            'category_id' => $categoryId,
            'amount' => $amount,
            'period' => 'monthly',
            'year' => $now->year,
            'month' => $now->month,
        ]);
    }
}
