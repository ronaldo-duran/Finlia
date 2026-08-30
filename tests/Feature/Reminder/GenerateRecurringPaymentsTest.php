<?php

namespace Tests\Feature\Reminder;

use App\Enums\Frequency;
use App\Models\Account;
use App\Models\Household;
use App\Models\RecurringExpense;
use App\Models\User;
use App\Services\HouseholdService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Generación automática de pagos recurrentes (Épica 9, ADR-0018): el
 * comando del Scheduler reutiliza markAsPaid() — gasto real con su cuenta
 * + avance de fecha, una sola ocurrencia por corrida.
 */
class GenerateRecurringPaymentsTest extends TestCase
{
    use RefreshDatabase;

    private Household $household;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->household = app(HouseholdService::class)->createHousehold($this->owner->id, 'Hogar A');
    }

    private function recurring(array $attributes = []): RecurringExpense
    {
        return $this->household->recurringExpenses()->create(array_merge([
            'name' => 'Arriendo',
            'amount' => 1200000,
            'frequency' => Frequency::Monthly->value,
            'next_date' => Carbon::now(config('app.timezone'))->subDays(3)->toDateString(),
            'is_active' => true,
            'auto_generate' => true,
        ], $attributes));
    }

    public function test_registra_el_gasto_con_la_fecha_real_de_la_ocurrencia(): void
    {
        // withInitialBalance: el saldo se recomputa desde los movimientos
        // (ADR-0012), así que el inicial debe ser el de partida real.
        $account = Account::factory()
            ->withInitialBalance(5000000)
            ->create(['household_id' => $this->household->id]);
        $originalDate = Carbon::now(config('app.timezone'))->subDays(3)->startOfDay();
        $recurring = $this->recurring(['account_id' => $account->id]);

        $this->artisan('finlia:generate-recurring-payments')->assertSuccessful();

        // El gasto existe, con la fecha de la ocurrencia vencida (no hoy).
        // La fecha se compara vía cast: SQLite guarda "… 00:00:00" crudo.
        $expense = $this->household->expenses()->first();
        $this->assertNotNull($expense);
        $this->assertSame($originalDate->toDateString(), $expense->date->toDateString());
        $this->assertSame(1200000.0, (float) $expense->amount);

        // La fecha avanzó una frecuencia y el saldo de la cuenta bajó.
        $this->assertSame(
            $originalDate->copy()->addMonthNoOverflow()->toDateString(),
            $recurring->fresh()->next_date->toDateString(),
        );
        $this->assertSame(3800000.0, (float) $account->fresh()->current_balance);
    }

    public function test_sin_cuenta_asociada_solo_avanza_la_fecha(): void
    {
        $recurring = $this->recurring(['account_id' => null]);
        $originalDate = Carbon::now(config('app.timezone'))->subDays(3)->startOfDay();

        $this->artisan('finlia:generate-recurring-payments')->assertSuccessful();

        $this->assertSame(0, $this->household->expenses()->count());
        $this->assertSame(
            $originalDate->copy()->addMonthNoOverflow()->toDateString(),
            $recurring->fresh()->next_date->toDateString(),
        );
    }

    public function test_no_toca_lo_que_no_tiene_generacion_automatica(): void
    {
        $manual = $this->recurring(['auto_generate' => false]);
        $pausado = $this->recurring(['is_active' => false]);
        $futuro = $this->recurring([
            'next_date' => Carbon::now(config('app.timezone'))->addDays(5)->toDateString(),
        ]);

        $this->artisan('finlia:generate-recurring-payments')->assertSuccessful();

        foreach ([$manual, $pausado, $futuro] as $recurring) {
            $this->assertSame(
                $recurring->next_date->toDateString(),
                $recurring->fresh()->next_date->toDateString(),
            );
        }

        $this->assertSame(0, $this->household->expenses()->count());
    }

    public function test_una_corrida_regulariza_una_sola_ocurrencia_por_recurrente(): void
    {
        // Vencido hace 3 meses: genera la MÁS vencida y queda vencido aún,
        // para recuperarse en corridas siguientes (sin ráfaga con fecha hoy).
        $account = Account::factory()
            ->withInitialBalance(5000000)
            ->create(['household_id' => $this->household->id]);
        $originalDate = Carbon::now(config('app.timezone'))->subMonths(3)->startOfDay();
        $recurring = $this->recurring([
            'account_id' => $account->id,
            'next_date' => $originalDate->toDateString(),
        ]);

        $this->artisan('finlia:generate-recurring-payments')->assertSuccessful();

        $this->assertSame(1, $this->household->expenses()->count());
        $this->assertSame(
            $originalDate->copy()->addMonthNoOverflow()->toDateString(),
            $recurring->fresh()->next_date->toDateString(),
        );
    }

    public function test_la_segunda_corrida_no_duplica(): void
    {
        $account = Account::factory()
            ->withInitialBalance(5000000)
            ->create(['household_id' => $this->household->id]);
        $this->recurring(['account_id' => $account->id]);

        $this->artisan('finlia:generate-recurring-payments')->assertSuccessful();
        $this->artisan('finlia:generate-recurring-payments')->assertSuccessful();

        // La segunda corrida ya no encuentra nada vencido.
        $this->assertSame(1, $this->household->expenses()->count());
        $this->assertSame(3800000.0, (float) $account->fresh()->current_balance);
    }
}
