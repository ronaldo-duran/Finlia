<?php

namespace Tests\Feature\Movement;

use App\Enums\DebtPaymentType;
use App\Enums\Frequency;
use App\Models\Account;
use App\Models\Debt;
use App\Models\Expense;
use App\Models\Income;
use App\Models\User;
use App\Services\DebtService;
use App\Services\HouseholdService;
use App\Services\MovementSummaryService;
use App\Services\RecurringExpenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "Últimos movimientos": la columna `date` es DATE (sin hora), así que dentro
 * de un mismo día no hay orden natural. Sin un desempate estable, el
 * movimiento recién registrado puede quedarse fuera de la lista.
 */
class RecentMovementsTest extends TestCase
{
    use RefreshDatabase;

    private function setup1(): array
    {
        $owner = User::factory()->create();
        $household = app(HouseholdService::class)->createHousehold($owner->id, 'Hogar A');
        $account = Account::factory()->withInitialBalance(5000000)->create(['household_id' => $household->id]);

        return [$owner, $household, $account];
    }

    public function test_respeta_el_limite_pedido(): void
    {
        [$owner, $household, $account] = $this->setup1();

        // 6 gastos y 6 ingresos el mismo día.
        Expense::factory()->count(6)->create([
            'household_id' => $household->id, 'user_id' => $owner->id,
            'account_id' => $account->id, 'date' => now()->toDateString(),
        ]);
        Income::factory()->count(6)->create([
            'household_id' => $household->id, 'user_id' => $owner->id,
            'account_id' => $account->id, 'date' => now()->toDateString(),
        ]);

        $recent = app(MovementSummaryService::class)->recentMovements($household->id, 6);

        // Se toman N de cada tabla y se mezclan: sin recorte final salen 12.
        $this->assertCount(6, $recent);
    }

    public function test_dentro_del_mismo_dia_manda_lo_registrado_mas_tarde(): void
    {
        [$owner, $household, $account] = $this->setup1();
        $hoy = now()->toDateString();

        // Se inserta primero el de las 18:00 y después el de las 10:00, para
        // que el orden correcto NO coincida con el de inserción: así el test
        // no depende de que el motor devuelva los empates en orden de rowid
        // (SQLite lo hace, MySQL no garantiza nada).
        $tarde = Expense::factory()->create([
            'household_id' => $household->id, 'user_id' => $owner->id,
            'account_id' => $account->id, 'date' => $hoy, 'description' => 'De las 18:00',
        ]);
        $tarde->forceFill(['created_at' => $hoy.' 18:00:00'])->save();

        $temprano = Expense::factory()->create([
            'household_id' => $household->id, 'user_id' => $owner->id,
            'account_id' => $account->id, 'date' => $hoy, 'description' => 'De las 10:00',
        ]);
        $temprano->forceFill(['created_at' => $hoy.' 10:00:00'])->save();

        $recent = app(MovementSummaryService::class)->recentMovements($household->id, 10);

        // `date` no tiene hora: el desempate tiene que salir de created_at.
        $this->assertSame('De las 18:00', $recent->first()['description']);
        $this->assertSame($tarde->id, $recent->first()['id']);
    }

    public function test_los_ingresos_se_intercalan_con_los_gastos_por_hora(): void
    {
        [$owner, $household, $account] = $this->setup1();
        $hoy = now()->toDateString();

        $gasto = Expense::factory()->create([
            'household_id' => $household->id, 'user_id' => $owner->id,
            'account_id' => $account->id, 'date' => $hoy, 'description' => 'Gasto 09:00',
        ]);
        $gasto->forceFill(['created_at' => $hoy.' 09:00:00'])->save();

        $ingreso = Income::factory()->create([
            'household_id' => $household->id, 'user_id' => $owner->id,
            'account_id' => $account->id, 'date' => $hoy, 'description' => 'Ingreso 20:00',
        ]);
        $ingreso->forceFill(['created_at' => $hoy.' 20:00:00'])->save();

        $recent = app(MovementSummaryService::class)->recentMovements($household->id, 10);

        // Al mezclar las dos tablas el orden por hora tiene que mantenerse.
        $this->assertSame('Ingreso 20:00', $recent->first()['description']);
        $this->assertSame('Gasto 09:00', $recent->last()['description']);
    }

    public function test_marcar_pagado_un_recurrente_aparece_en_los_ultimos_movimientos(): void
    {
        [$owner, $household, $account] = $this->setup1();
        $hoy = now()->toDateString();

        // Día cargado: ya hay 8 gastos con la misma fecha.
        Expense::factory()->count(8)->create([
            'household_id' => $household->id, 'user_id' => $owner->id,
            'account_id' => $account->id, 'date' => $hoy,
        ]);

        $recurrente = $household->recurringExpenses()->create([
            'name' => 'Arriendo',
            'amount' => 1200000,
            'frequency' => Frequency::Monthly->value,
            'next_date' => $hoy,
            'account_id' => $account->id,
        ]);

        app(RecurringExpenseService::class)->markAsPaid($recurrente, $owner);

        $recent = app(MovementSummaryService::class)->recentMovements($household->id, 6);

        // El pago recién registrado tiene que verse, no perderse entre los del día.
        $this->assertNotNull(
            $recent->firstWhere('description', 'Arriendo (pago recurrente)'),
            'El pago del recurrente no aparece en los últimos movimientos.',
        );
    }

    public function test_un_pago_de_deuda_tambien_aparece(): void
    {
        [$owner, $household, $account] = $this->setup1();
        $hoy = now()->toDateString();

        Expense::factory()->count(8)->create([
            'household_id' => $household->id, 'user_id' => $owner->id,
            'account_id' => $account->id, 'date' => $hoy,
        ]);

        $debt = Debt::factory()->create([
            'household_id' => $household->id,
            'name' => 'Tarjeta',
            'original_amount' => 1000000,
            'current_balance' => 1000000,
        ]);

        app(DebtService::class)->registerPayment($debt, [
            'amount' => 200000,
            'date' => $hoy,
            'type' => DebtPaymentType::Scheduled->value,
            'account_id' => $account->id,
        ], $owner);

        $recent = app(MovementSummaryService::class)->recentMovements($household->id, 6);

        $this->assertNotNull(
            $recent->firstWhere('description', 'Tarjeta (pago de deuda)'),
            'El pago de deuda no aparece en los últimos movimientos.',
        );
    }
}
