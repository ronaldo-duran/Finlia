<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\Account;
use App\Models\Budget;
use App\Models\Category;
use App\Models\CreditCard;
use App\Models\Debt;
use App\Models\DebtPayment;
use App\Models\ExpectedIncome;
use App\Models\Expense;
use App\Models\Household;
use App\Models\HouseholdInvitation;
use App\Models\Income;
use App\Models\RecurringExpense;
use App\Models\Reminder;
use App\Models\SavingsGoal;
use App\Models\SavingsGoalContribution;
use App\Models\Transfer;
use App\Models\User;
use App\Services\HouseholdService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Barrido de aislamiento multi-hogar (Épica 11).
 *
 * El aislamiento entre hogares es la amenaza #1 del proyecto (CLAUDE.md §6):
 * un usuario nunca debe ver ni tocar datos de un hogar al que no pertenece,
 * ni manipulando el ID de la URL.
 *
 * Los tests por recurso ya cubren sus casos principales; este barrido existe
 * para lo que aquellos no pueden garantizar: que **ninguna ruta con
 * parámetro de modelo se quede sin autorizar**. Recorre todas de una vez, de
 * modo que una ruta nueva sin `authorize()` se detecta aquí aunque nadie
 * escriba su test de aislamiento.
 *
 * Las **lecturas** (GET) deben responder 403 (Policy) o 404 (binding acotado
 * al hogar): ambos niegan sin filtrar si el recurso existe.
 *
 * En las **escrituras** un 302 no implica bypass: con el cuerpo vacío salta
 * primero la validación del Form Request y Laravel redirige con los errores.
 * Se comprobó enviando un cuerpo válido a `PUT /cuentas/{id}` de otro hogar:
 * responde 403 y la fila no cambia. Por eso ahí no se exige un código
 * concreto sino la propiedad que de verdad importa — que ninguna fila ajena
 * se altere ni desaparezca — además de que nunca respondan 200.
 */
class HouseholdIsolationSweepTest extends TestCase
{
    use RefreshDatabase;

    private User $intruso;

    /** @var array<string, mixed> */
    private array $ajeno = [];

    /**
     * Hogar "víctima" con un recurso de cada tipo, y un intruso con su propio
     * hogar (así pasa los middleware y llega hasta la autorización real).
     */
    protected function setUp(): void
    {
        parent::setUp();

        $victima = User::factory()->create();
        $hogar = app(HouseholdService::class)->createHousehold($victima->id, 'Hogar Víctima');

        $this->intruso = User::factory()->create();
        app(HouseholdService::class)->createHousehold($this->intruso->id, 'Hogar Intruso');

        $cuenta = Account::factory()->create(['household_id' => $hogar->id]);
        $categoria = Category::where('household_id', $hogar->id)->first()
            ?? Category::factory()->create(['household_id' => $hogar->id]);
        $deuda = Debt::factory()->create(['household_id' => $hogar->id, 'status' => 'active']);
        $meta = SavingsGoal::factory()->create(['household_id' => $hogar->id, 'status' => 'active']);
        $otraCuenta = Account::factory()->create(['household_id' => $hogar->id]);

        $this->ajeno = [
            'household' => $hogar,
            'account' => $cuenta,
            'category' => $categoria,
            'debt' => $deuda,
            'savingsGoal' => $meta,
            'expense' => Expense::factory()->create([
                'household_id' => $hogar->id, 'account_id' => $cuenta->id, 'user_id' => $victima->id,
            ]),
            'income' => Income::factory()->create([
                'household_id' => $hogar->id, 'account_id' => $cuenta->id, 'user_id' => $victima->id,
            ]),
            'budget' => Budget::factory()->create(['household_id' => $hogar->id]),
            'expectedIncome' => ExpectedIncome::factory()->create(['household_id' => $hogar->id]),
            'recurringExpense' => RecurringExpense::factory()->create(['household_id' => $hogar->id]),
            'reminder' => Reminder::factory()->create(['household_id' => $hogar->id]),
            'transfer' => Transfer::factory()->create([
                'household_id' => $hogar->id, 'user_id' => $victima->id,
                'from_account_id' => $cuenta->id, 'to_account_id' => $otraCuenta->id,
            ]),
            'payment' => DebtPayment::factory()->create([
                'household_id' => $hogar->id, 'debt_id' => $deuda->id,
            ]),
            'contribution' => SavingsGoalContribution::factory()->create([
                'savings_goal_id' => $meta->id,
            ]),
            'invitation' => HouseholdInvitation::factory()->create(['household_id' => $hogar->id]),
            'creditCard' => CreditCard::factory()->create(['account_id' => $cuenta->id]),
            'victima' => $victima,
        ];
    }

    /**
     * Todas las rutas con parámetro de modelo acotado al hogar.
     *
     * @return list<array{string, string}>
     */
    private function rutasAjenas(): array
    {
        $a = $this->ajeno;

        return [
            ['GET', "/cuentas/{$a['account']->id}"],
            ['GET', "/cuentas/{$a['account']->id}/edit"],
            ['PUT', "/cuentas/{$a['account']->id}"],
            ['DELETE', "/cuentas/{$a['account']->id}"],
            ['PUT', "/cuentas/{$a['account']->id}/tarjeta"],
            ['DELETE', "/cuentas/{$a['account']->id}/tarjeta"],

            ['PUT', "/categorias/{$a['category']->id}"],
            ['DELETE', "/categorias/{$a['category']->id}"],

            ['GET', "/deudas/{$a['debt']->id}"],
            ['PUT', "/deudas/{$a['debt']->id}"],
            ['DELETE', "/deudas/{$a['debt']->id}"],
            ['POST', "/deudas/{$a['debt']->id}/pagos"],
            ['DELETE', "/deudas/{$a['debt']->id}/pagos/{$a['payment']->id}"],
            ['POST', "/deudas/{$a['debt']->id}/refinanciacion"],

            ['GET', "/gastos/{$a['expense']->id}/editar"],
            ['PUT', "/gastos/{$a['expense']->id}"],
            ['DELETE', "/gastos/{$a['expense']->id}"],

            ['GET', "/ingresos/{$a['income']->id}/editar"],
            ['PUT', "/ingresos/{$a['income']->id}"],
            ['DELETE', "/ingresos/{$a['income']->id}"],

            ['PUT', "/ingresos-esperados/{$a['expectedIncome']->id}"],
            ['DELETE', "/ingresos-esperados/{$a['expectedIncome']->id}"],

            ['GET', "/metas/{$a['savingsGoal']->id}"],
            ['GET', "/metas/{$a['savingsGoal']->id}/editar"],
            ['PUT', "/metas/{$a['savingsGoal']->id}"],
            ['DELETE', "/metas/{$a['savingsGoal']->id}"],
            ['POST', "/metas/{$a['savingsGoal']->id}/aportes"],
            ['DELETE', "/metas/{$a['savingsGoal']->id}/aportes/{$a['contribution']->id}"],
            ['POST', "/metas/{$a['savingsGoal']->id}/archivar"],
            ['POST', "/metas/{$a['savingsGoal']->id}/completar"],
            ['POST', "/metas/{$a['savingsGoal']->id}/pausar"],
            ['POST', "/metas/{$a['savingsGoal']->id}/reactivar"],

            ['GET', "/presupuestos/{$a['budget']->id}/edit"],
            ['PUT', "/presupuestos/{$a['budget']->id}"],
            ['DELETE', "/presupuestos/{$a['budget']->id}"],

            ['PUT', "/recordatorios/{$a['reminder']->id}"],
            ['DELETE', "/recordatorios/{$a['reminder']->id}"],
            ['POST', "/recordatorios/{$a['reminder']->id}/completar"],

            ['PUT', "/recurrentes/{$a['recurringExpense']->id}"],
            ['DELETE', "/recurrentes/{$a['recurringExpense']->id}"],
            ['POST', "/recurrentes/{$a['recurringExpense']->id}/pagar"],

            ['GET', "/transferencias/{$a['transfer']->id}/editar"],
            ['PUT', "/transferencias/{$a['transfer']->id}"],
            ['DELETE', "/transferencias/{$a['transfer']->id}"],

            ['GET', "/hogares/{$a['household']->id}"],
            ['GET', "/hogares/{$a['household']->id}/editar"],
            ['PUT', "/hogares/{$a['household']->id}"],
            ['DELETE', "/hogares/{$a['household']->id}"],
            ['POST', "/hogares/{$a['household']->id}/activar"],
            ['POST', "/hogares/{$a['household']->id}/invitaciones"],
            ['DELETE', "/hogares/{$a['household']->id}/invitaciones/{$a['invitation']->id}"],
            ['DELETE', "/hogares/{$a['household']->id}/miembros/{$a['victima']->id}"],
        ];
    }

    public function test_ninguna_lectura_del_hogar_ajeno_responde_al_intruso(): void
    {
        $filtradas = [];

        foreach ($this->rutasAjenas() as [$metodo, $uri]) {
            if ($metodo !== 'GET') {
                continue;
            }

            $status = $this->actingAs($this->intruso)->call($metodo, $uri)->getStatusCode();

            if (! in_array($status, [403, 404], true)) {
                $filtradas[] = "{$metodo} {$uri} → {$status}";
            }
        }

        $this->assertSame(
            [],
            $filtradas,
            "Lecturas que NO negaron el acceso a un hogar ajeno:\n  ".implode("\n  ", $filtradas)."\n",
        );
    }

    public function test_ninguna_escritura_del_intruso_altera_datos_ajenos(): void
    {
        $antes = $this->huellaDatosAjenos();
        $ejecutadas = [];

        foreach ($this->rutasAjenas() as [$metodo, $uri]) {
            if ($metodo === 'GET') {
                continue;
            }

            $status = $this->actingAs($this->intruso)->call($metodo, $uri)->getStatusCode();

            // Un 200 en una escritura ajena sería la acción completada.
            if ($status === 200) {
                $ejecutadas[] = "{$metodo} {$uri} → 200";
            }
        }

        $this->assertSame([], $ejecutadas, 'Escrituras ajenas que respondieron 200: '.implode(', ', $ejecutadas));
        $this->assertSame(
            $antes,
            $this->huellaDatosAjenos(),
            'Una escritura del intruso modificó o borró datos del hogar ajeno.',
        );
    }

    /**
     * Huella de todas las filas del hogar ajeno: si algo se modifica o se
     * borra, cambia.
     *
     * @return array<string, mixed>
     */
    private function huellaDatosAjenos(): array
    {
        $hogarId = $this->ajeno['household']->id;

        $huella = [];

        foreach ([
            'accounts' => Account::class,
            'categories' => Category::class,
            'debts' => Debt::class,
            'savings_goals' => SavingsGoal::class,
            'expenses' => Expense::class,
            'incomes' => Income::class,
            'budgets' => Budget::class,
            'expected_incomes' => ExpectedIncome::class,
            'recurring_expenses' => RecurringExpense::class,
            'reminders' => Reminder::class,
            'transfers' => Transfer::class,
            'debt_payments' => DebtPayment::class,
        ] as $etiqueta => $modelo) {
            // `getRawOriginal()` + JSON: se comparan valores, no identidades
            // de objetos (dos Carbon con la misma fecha son objetos distintos).
            $huella[$etiqueta] = $modelo::where('household_id', $hogarId)
                ->orderBy('id')
                ->get()
                ->map(fn ($fila) => json_encode($fila->getRawOriginal()))
                ->toArray();
        }

        $huella['households'] = Household::whereKey($hogarId)->get()
            ->map(fn ($fila) => json_encode($fila->getRawOriginal()))
            ->toArray();
        $huella['miembros'] = $this->ajeno['household']->members()->orderBy('users.id')->pluck('users.id')->toArray();

        return $huella;
    }

    public function test_el_intruso_no_ve_datos_ajenos_en_los_listados(): void
    {
        $descripcionAjena = 'SECRETO-DEL-HOGAR-AJENO';

        Expense::factory()->create([
            'household_id' => $this->ajeno['household']->id,
            'account_id' => $this->ajeno['account']->id,
            'user_id' => $this->ajeno['victima']->id,
            'description' => $descripcionAjena,
        ]);

        foreach (['/dashboard', '/movimientos', '/reportes', '/cuentas', '/deudas', '/metas', '/presupuestos'] as $uri) {
            $this->actingAs($this->intruso)
                ->get($uri)
                ->assertOk()
                ->assertDontSee($descripcionAjena)
                ->assertDontSee($this->ajeno['account']->name)
                ->assertDontSee($this->ajeno['debt']->name);
        }
    }
}
