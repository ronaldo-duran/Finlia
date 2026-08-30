<?php

namespace Tests\Unit;

use App\Enums\DebtStatus;
use App\Enums\DebtType;
use App\Enums\Frequency;
use App\Enums\ReminderSource;
use App\Enums\ReminderStatus;
use App\Enums\SavingsGoalContributionType;
use App\Models\Household;
use App\Models\Reminder;
use App\Models\User;
use App\Services\HouseholdService;
use App\Services\ReminderService;
use App\Services\SavingsGoalService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ReminderService (Épica 9, ADR-0027): la lista unificada deriva el estado
 * contra hoy, así que nunca caduca sin cron. Fecha de referencia fija para
 * que los estados sean deterministas.
 */
class ReminderServiceTest extends TestCase
{
    use RefreshDatabase;

    private Household $household;

    private ReminderService $service;

    /** "Hoy" fijo para todos los casos. */
    private Carbon $today;

    protected function setUp(): void
    {
        parent::setUp();

        $owner = User::factory()->create();
        $this->household = app(HouseholdService::class)->createHousehold($owner->id, 'Hogar A');
        $this->service = app(ReminderService::class);
        $this->today = Carbon::parse('2026-09-15');
    }

    private function reminder(array $attributes = []): Reminder
    {
        return $this->household->reminders()->create(array_merge([
            'title' => 'Tecnomecánica',
            'due_date' => '2026-10-01',
        ], $attributes));
    }

    public function test_el_estado_se_deriva_de_la_fecha(): void
    {
        $this->reminder(['title' => 'Vencida', 'due_date' => '2026-09-10']);
        $this->reminder(['title' => 'Hoy', 'due_date' => '2026-09-15']);
        $this->reminder(['title' => 'En 5 días', 'due_date' => '2026-09-20']);
        $this->reminder(['title' => 'Lejos', 'due_date' => '2026-12-01']);

        $byTitle = $this->service->list($this->household->id, $this->today)
            ->keyBy('title');

        $this->assertSame(ReminderStatus::Overdue, $byTitle['Vencida']['status']);
        $this->assertSame(ReminderStatus::Upcoming, $byTitle['Hoy']['status']);
        $this->assertSame(ReminderStatus::Upcoming, $byTitle['En 5 días']['status']);
        $this->assertSame(ReminderStatus::Pending, $byTitle['Lejos']['status']);
    }

    public function test_avisos_completados_y_recurrentes_pausados_no_aparecen(): void
    {
        $this->reminder(['title' => 'Hecho', 'status' => ReminderStatus::Completed->value]);
        $this->household->recurringExpenses()->create([
            'name' => 'Suscripción pausada', 'amount' => 20000,
            'frequency' => Frequency::Monthly->value,
            'next_date' => '2026-09-16', 'is_active' => false,
        ]);

        $items = $this->service->list($this->household->id, $this->today);

        $this->assertSame([], $items->where('title', 'Hecho')->all());
        $this->assertSame([], $items->where('title', 'Suscripción pausada')->all());
    }

    public function test_la_deuda_con_pago_este_mes_avisa_la_cuota_del_siguiente(): void
    {
        // forceCreate: current_balance es derivado (ADR-0020), no fillable.
        $debt = $this->household->debts()->forceCreate([
            'name' => 'Tarjeta Davivienda',
            'type' => DebtType::CreditCard->value,
            'original_amount' => 5000000,
            'current_balance' => 4800000,
            'minimum_payment' => 200000,
            'due_day' => 5,
            'status' => DebtStatus::Active->value,
        ]);
        // Cuota de septiembre (día 5) ya pagada.
        $debt->payments()->forceCreate([
            'household_id' => $this->household->id,
            'amount' => 200000,
            'date' => '2026-09-05',
        ]);

        $item = $this->service->list($this->household->id, $this->today)
            ->firstWhere('source', ReminderSource::Debt);

        $this->assertNotNull($item);
        $this->assertSame('2026-10-05', $item['due_date']->toDateString());
        $this->assertSame(200000.0, $item['amount']);
    }

    public function test_la_deuda_impaga_con_dia_pasado_aparece_vencida(): void
    {
        $this->household->debts()->forceCreate([
            'name' => 'Préstamo coche',
            'type' => DebtType::Vehicle->value,
            'original_amount' => 40000000,
            'current_balance' => 35000000,
            'minimum_payment' => 900000,
            'due_day' => 5,
            'status' => DebtStatus::Active->value,
        ]);

        $item = $this->service->list($this->household->id, $this->today)
            ->firstWhere('source', ReminderSource::Debt);

        $this->assertNotNull($item);
        $this->assertSame(ReminderStatus::Overdue, $item['status']);
        $this->assertSame('2026-09-05', $item['due_date']->toDateString());
    }

    public function test_deuda_sin_cuota_conocida_no_genera_aviso(): void
    {
        $this->household->debts()->forceCreate([
            'name' => 'Deuda familiar',
            'type' => DebtType::Family->value,
            'original_amount' => 1000000,
            'current_balance' => 800000,
            'minimum_payment' => null,
            'planned_payment' => null,
            'due_day' => 10,
            'status' => DebtStatus::Active->value,
        ]);

        $this->assertNull(
            $this->service->list($this->household->id, $this->today)
                ->firstWhere('source', ReminderSource::Debt)
        );
    }

    public function test_la_meta_vencida_aparece_con_lo_que_falta_como_monto(): void
    {
        // El ahorrado no se teclea: aporte real y current_amount derivado
        // (ADR-0025), igual que haría el usuario desde la UI.
        $goal = $this->household->savingsGoals()->create([
            'name' => 'Viaje San Andrés',
            'target_amount' => 3000000,
            'target_date' => '2026-09-01',
            'status' => 'active',
        ]);
        app(SavingsGoalService::class)->registerContribution($goal, [
            'amount' => 1200000,
            'date' => '2026-08-01',
            'type' => SavingsGoalContributionType::Deposit->value,
        ]);

        $item = $this->service->list($this->household->id, $this->today)
            ->firstWhere('source', ReminderSource::SavingsGoal);

        $this->assertNotNull($item);
        $this->assertSame(ReminderStatus::Overdue, $item['status']);
        $this->assertSame(1800000.0, $item['amount']);
    }

    public function test_el_resumen_cuenta_vencidas_y_proximas_no_las_lejanas(): void
    {
        $this->reminder(['title' => 'Vencida 1', 'due_date' => '2026-09-01']);
        $this->reminder(['title' => 'Próxima', 'due_date' => '2026-09-18']);
        $this->reminder(['title' => 'Lejos', 'due_date' => '2026-11-30']);

        $summary = $this->service->summary($this->household->id, $this->today);

        $this->assertSame(1, $summary['overdue']);
        $this->assertSame(1, $summary['upcoming']);
        $this->assertSame(2, $summary['attention']);
        $this->assertSame(3, $summary['total']);
    }

    public function test_la_lista_ordena_vencidas_primero(): void
    {
        $this->reminder(['title' => 'Lejos', 'due_date' => '2026-12-01']);
        $this->reminder(['title' => 'Vencida', 'due_date' => '2026-09-01']);
        $this->reminder(['title' => 'Próxima', 'due_date' => '2026-09-17']);

        $titles = $this->service->list($this->household->id, $this->today)
            ->pluck('title');

        $this->assertSame(['Vencida', 'Próxima', 'Lejos'], $titles->all());
    }

    public function test_atender_un_anual_avanza_la_fecha_un_anio_y_sigue_pendiente(): void
    {
        $anual = $this->reminder([
            'title' => 'SOAT', 'due_date' => '2026-09-20',
            'frequency' => Frequency::Yearly->value,
        ]);

        $finished = $this->service->complete($anual);

        $this->assertFalse($finished);
        $this->assertSame('2027-09-20', $anual->fresh()->due_date->toDateString());
        $this->assertSame(ReminderStatus::Pending, $anual->fresh()->status);
    }

    public function test_atender_un_suelto_lo_deja_completado(): void
    {
        $suelto = $this->reminder(['title' => 'Renovar pasaporte', 'due_date' => '2026-10-10']);

        $finished = $this->service->complete($suelto);

        $this->assertTrue($finished);
        $this->assertSame(ReminderStatus::Completed, $suelto->fresh()->status);
    }

    public function test_los_recordatorios_no_se_mezclan_entre_hogares(): void
    {
        $other = app(HouseholdService::class)->createHousehold(
            User::factory()->create()->id, 'Hogar B',
        );
        $other->reminders()->create(['title' => 'Secreto de B', 'due_date' => '2026-09-16']);

        $titles = $this->service->list($this->household->id, $this->today)->pluck('title');

        $this->assertNotContains('Secreto de B', $titles);
    }

    // ===== Resumen cacheado (campanita) =====

    public function test_el_resumen_cacheado_se_invalida_al_atender_un_aviso(): void
    {
        // Fechas relativas: cachedSummary() deriva contra HOY real (no
        // acepta referencia), así que el caso no puede acoplarse al $today
        // fijo de los demás tests.
        $vencido = $this->reminder([
            'title' => 'SOAT', 'due_date' => now()->subDay()->toDateString(),
        ]);

        $this->assertSame(1, $this->service->cachedSummary($this->household->id)['overdue']);

        // Sin la invalidación del observer, la segunda lectura devolvería
        // el conteo cacheado (1) en lugar de recalcular.
        $this->service->complete($vencido);

        $this->assertSame(0, $this->service->cachedSummary($this->household->id)['overdue']);
    }

    public function test_pagar_la_cuota_de_la_deuda_invalida_el_resumen_cacheado(): void
    {
        $debt = $this->household->debts()->forceCreate([
            'name' => 'Tarjeta',
            'type' => DebtType::CreditCard->value,
            'original_amount' => 3000000,
            'current_balance' => 2500000,
            'minimum_payment' => 200000,
            'due_day' => 5,
            'status' => DebtStatus::Active->value,
        ]);

        // Día 5 de este mes ya pasó y no hay pago: la cuota está vencida.
        $this->assertSame(1, $this->service->cachedSummary($this->household->id)['overdue']);

        $debt->payments()->forceCreate([
            'household_id' => $this->household->id,
            'amount' => 200000,
            'date' => now()->toDateString(),
        ]);

        // Pagada la cuota del mes, el aviso pasa a la del siguiente.
        $this->assertSame(0, $this->service->cachedSummary($this->household->id)['overdue']);
    }
}
