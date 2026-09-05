<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Debt;
use App\Models\DebtPayment;
use App\Models\Expense;
use App\Models\Income;
use App\Models\SavingsGoal;
use App\Models\SavingsGoalContribution;
use App\Models\User;
use App\Services\HouseholdService;
use App\Services\MovementSummaryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Presupuesto de consultas de las pantallas calientes (Épica 11).
 *
 * Su valor no es medir latencia, sino **fijar un techo**: un N+1 que se cuele
 * en un bucle de Blade o en un service dispara estos topes mucho antes de que
 * se note en producción. Los topes tienen holgura sobre el consumo real para
 * no romperse por un `where` de más; lo que no toleran es un salto de orden
 * de magnitud.
 *
 * Consumo medido en aislamiento (una petición por test, sin cachés calientes
 * de peticiones previas) al cerrar la deuda del roadmap:
 *   /dashboard      50 → 34        /reportes         28 → 22
 *   /cuentas/{id}   22 → 13        monthlyTrend(6)   12 → 2
 *
 * Cada tope queda por DEBAJO de la cifra anterior: si alguien revirtiera uno
 * de los arreglos, el test falla en vez de pasar por los pelos.
 */
class PerformanceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Hogar con volumen suficiente para que un N+1 se note: si el número de
     * consultas creciera con las filas, estos topes no se cumplirían.
     */
    private function seedHousehold(): array
    {
        $user = User::factory()->create();
        $household = app(HouseholdService::class)->createHousehold($user->id, 'Hogar');
        $account = Account::factory()->create(['household_id' => $household->id]);

        $categories = Category::where('household_id', $household->id)->take(5)->get();

        if ($categories->isEmpty()) {
            $categories = Category::factory()->count(5)->create(['household_id' => $household->id]);
        }

        for ($i = 0; $i < 40; $i++) {
            $date = now()->subDays($i % 120)->format('Y-m-d');

            Expense::factory()->create([
                'household_id' => $household->id,
                'account_id' => $account->id,
                'user_id' => $user->id,
                'category_id' => $categories->random()->id,
                'date' => $date,
            ]);

            Income::factory()->create([
                'household_id' => $household->id,
                'account_id' => $account->id,
                'user_id' => $user->id,
                'date' => $date,
            ]);
        }

        foreach (range(1, 5) as $i) {
            $debt = Debt::factory()->create(['household_id' => $household->id, 'status' => 'active']);
            DebtPayment::factory()->create(['household_id' => $household->id, 'debt_id' => $debt->id]);

            $goal = SavingsGoal::factory()->create(['household_id' => $household->id, 'status' => 'active']);
            SavingsGoalContribution::factory()->create(['savings_goal_id' => $goal->id]);
        }

        return [$user, $household, $account];
    }

    private function countQueries(callable $action): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $action();

        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    public function test_el_panel_no_dispara_una_consulta_por_fila(): void
    {
        [$user] = $this->seedHousehold();

        $queries = $this->countQueries(fn () => $this->actingAs($user)->get('/dashboard')->assertOk());

        // Antes: 50.
        $this->assertLessThanOrEqual(40, $queries, "El panel gastó {$queries} consultas.");
    }

    public function test_reportes_no_recalcula_los_mismos_totales(): void
    {
        [$user] = $this->seedHousehold();

        $queries = $this->countQueries(fn () => $this->actingAs($user)->get('/reportes')->assertOk());

        // Antes: 28.
        $this->assertLessThanOrEqual(25, $queries, "Reportes gastó {$queries} consultas.");
    }

    public function test_el_detalle_de_cuenta_precarga_las_categorias(): void
    {
        [$user, , $account] = $this->seedHousehold();

        // La vista recorre 10 ingresos y 10 gastos leyendo `category?->name`:
        // sin eager loading serían ~20 consultas extra por PK.
        $queries = $this->countQueries(
            fn () => $this->actingAs($user)->get("/cuentas/{$account->id}")->assertOk()
        );

        // Antes: 22.
        $this->assertLessThanOrEqual(16, $queries, "El detalle de cuenta gastó {$queries} consultas.");
    }

    /**
     * La propiedad que de verdad importa de `monthlyTotals`: el coste no
     * depende del número de meses. Antes eran dos consultas por mes.
     */
    public function test_la_tendencia_mensual_cuesta_lo_mismo_con_3_que_con_12_meses(): void
    {
        [, $household] = $this->seedHousehold();
        $movements = app(MovementSummaryService::class);

        $tresMeses = $this->countQueries(fn () => $movements->monthlyTrend($household->id, 3));
        $doceMeses = $this->countQueries(fn () => $movements->monthlyTrend($household->id, 12));

        $this->assertSame(
            $tresMeses,
            $doceMeses,
            "3 meses costaron {$tresMeses} consultas y 12 costaron {$doceMeses}: el coste crece con el rango.",
        );
        $this->assertLessThanOrEqual(4, $doceMeses);
    }
}
