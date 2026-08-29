<?php

namespace Tests\Feature\Report;

use App\Models\Account;
use App\Models\Category;
use App\Models\Expense;
use App\Models\Household;
use App\Models\User;
use App\Services\HouseholdService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Épica 8: pantalla de reportes — períodos comparables, insights y
 * exportación CSV, siempre acotada al hogar activo.
 */
class ReportTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: Household, 2: Account, 3: Category}
     */
    private function setupHousehold(string $name = 'Hogar A'): array
    {
        $owner = User::factory()->create();
        $household = app(HouseholdService::class)->createHousehold($owner->id, $name);
        $account = Account::factory()->create(['household_id' => $household->id]);
        $category = Category::create([
            'name' => 'Alimentación',
            'type' => 'expense',
            'household_id' => null,
            'is_default' => true,
        ]);

        return [$owner, $household, $account, $category];
    }

    public function test_guest_es_redirigido_al_login(): void
    {
        $this->get(route('reports.index'))->assertRedirect(route('login'));
        $this->get(route('reports.export', ['period' => 'month']))->assertRedirect(route('login'));
    }

    public function test_usuario_sin_hogar_es_redirigido_a_crear_hogar(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('reports.index'))
            ->assertRedirect(route('households.create'));
    }

    public function test_usuario_autenticado_ve_los_reportes(): void
    {
        [$owner] = $this->setupHousehold();

        $response = $this->actingAs($owner)->get(route('reports.index'));

        $response->assertOk();
        $response->assertSee('Reportes');
        $response->assertSee('Resumen');
        $response->assertSee('Observaciones');
        $response->assertSee('Exportar CSV');
        // Los cinco períodos comparables de la épica.
        $response->assertSee('Mes anterior');
        $response->assertSee('Últimos 3 meses');
        $response->assertSee('Últimos 6 meses');
        $response->assertSee('Año');
    }

    public function test_el_periodo_cambia_la_ventana_del_resumen(): void
    {
        [$owner, $household, $account, $category] = $this->setupHousehold();

        Expense::factory()->create([
            'household_id' => $household->id,
            'user_id' => $owner->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'amount' => 100000,
            'date' => now()->startOfMonth()->toDateString(),
        ]);

        // Mes actual: el gasto cuenta.
        $this->actingAs($owner)->get(route('reports.index', ['period' => 'month']))
            ->assertOk()
            ->assertSee('$ 100.000,00');

        // Mes anterior: la ventana se mueve y el gasto de este mes no cuenta.
        $this->actingAs($owner)->get(route('reports.index', ['period' => 'last_month']))
            ->assertOk()
            ->assertDontSee('$ 100.000,00');
    }

    public function test_periodo_invalido_no_pasa_validacion(): void
    {
        [$owner] = $this->setupHousehold();

        $this->actingAs($owner)->get(route('reports.index', ['period' => 'nonsense']))
            ->assertSessionHasErrors('period');
    }

    public function test_los_insights_reflejan_cambios_reales_entre_periodos(): void
    {
        [$owner, $household, $account, $category] = $this->setupHousehold();

        Expense::factory()->create([
            'household_id' => $household->id,
            'user_id' => $owner->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'amount' => 100000,
            'date' => now()->toDateString(),
        ]);

        Expense::factory()->create([
            'household_id' => $household->id,
            'user_id' => $owner->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'amount' => 250000,
            'date' => now()->subMonth()->toDateString(),
        ]);

        $this->actingAs($owner)->get(route('reports.index', ['period' => 'month']))
            ->assertOk()
            ->assertSee('Gastaste $ 150.000,00 menos');
    }

    public function test_export_csv_descarga_los_movimientos_del_periodo(): void
    {
        [$owner, $household, $account, $category] = $this->setupHousehold();

        Expense::factory()->create([
            'household_id' => $household->id,
            'user_id' => $owner->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'amount' => 123456.78,
            'date' => now()->toDateString(),
            'description' => 'Mercado de la semana',
        ]);

        $response = $this->actingAs($owner)->get(route('reports.export', ['period' => 'month']));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $csv = $response->streamedContent();

        // BOM UTF-8 (Excel) + cabeceras + fila del movimiento.
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('Fecha;Tipo;Categoría', $csv);
        $this->assertStringContainsString('Gasto', $csv);
        $this->assertStringContainsString('Mercado de la semana', $csv);
        $this->assertStringContainsString('123456,78', $csv);
    }

    public function test_el_csv_neutraliza_celdas_que_parecen_formulas(): void
    {
        // Inyección CSV (OWASP): un texto que empieza por =, +, - o @ se
        // evaluaría como fórmula al abrir el archivo en Excel. Se prefija
        // con ' para neutralizarlo.
        [$owner, $household, $account, $category] = $this->setupHousehold();

        Expense::factory()->create([
            'household_id' => $household->id,
            'user_id' => $owner->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'amount' => 10000,
            'date' => now()->toDateString(),
            'description' => '=HYPERLINK("http://ejemplo-malicioso.test";"clic aqui")',
        ]);

        $csv = $this->actingAs($owner)
            ->get(route('reports.export', ['period' => 'month']))
            ->streamedContent();

        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringNotContainsString(';"=HYPERLINK', $csv);
    }

    public function test_el_export_no_trunca_periodos_con_mas_de_200_movimientos(): void
    {
        // Regresión: `filtered()` tiene un tope de 200 pensado para la
        // pantalla de navegación; el CSV de un período completo no puede
        // salir recortado en silencio.
        [$owner, $household, $account, $category] = $this->setupHousehold();

        Expense::factory()
            ->count(205)
            ->sequence(fn () => [
                'household_id' => $household->id,
                'user_id' => $owner->id,
                'account_id' => $account->id,
                'category_id' => $category->id,
                'date' => fake()->dateTimeBetween(now()->startOfMonth(), now())->format('Y-m-d'),
            ])
            ->create();

        $csv = $this->actingAs($owner)
            ->get(route('reports.export', ['period' => 'month']))
            ->streamedContent();

        // Cabecera + 205 filas: cada fputcsv termina en "\n".
        $this->assertSame(206, substr_count($csv, "\n"));
    }

    public function test_el_export_de_un_hogar_no_incluye_movimientos_de_otros_hogares(): void
    {
        // Aislamiento multi-hogar (amenaza #1): el CSV sale siempre del
        // hogar activo del usuario autenticado.
        [$ownerA, $hogarA, $cuentaA] = $this->setupHousehold('Hogar A');

        Expense::factory()->create([
            'household_id' => $hogarA->id,
            'user_id' => $ownerA->id,
            'account_id' => $cuentaA->id,
            'amount' => 50000,
            'date' => now()->toDateString(),
            'description' => 'SecretoDelHogarA',
        ]);

        [$ownerB] = $this->setupHousehold('Hogar B');

        $csv = $this->actingAs($ownerB)
            ->get(route('reports.export', ['period' => 'month']))
            ->streamedContent();

        $this->assertStringNotContainsString('SecretoDelHogarA', $csv);
    }

    public function test_el_panel_muestra_deuda_total_y_ahorro_en_metas(): void
    {
        [$owner] = $this->setupHousehold();

        $this->actingAs($owner)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Deuda total')
            ->assertSee('Ahorro en metas')
            ->assertSee('Ver reportes');
    }
}
