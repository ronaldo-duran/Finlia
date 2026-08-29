<?php

declare(strict_types=1);

namespace Tests\Feature\Savings;

use App\Enums\SavingsGoalContributionType;
use App\Enums\SavingsGoalStatus;
use App\Models\SavingsGoal;
use App\Models\User;
use App\Services\HouseholdService;
use App\Services\SavingsGoalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Épica 7: metas de ahorro por HTTP — CRUD, aislamiento por hogar,
 * aportes/retiros y auto-completado (ADR-0025).
 */
class SavingsGoalTest extends TestCase
{
    use RefreshDatabase;

    private function setupHousehold(string $name = 'Hogar A'): array
    {
        $owner = User::factory()->create();
        $household = app(HouseholdService::class)->createHousehold($owner->id, $name);

        return [$owner, $household];
    }

    private function goalFor($household, array $attributes = []): SavingsGoal
    {
        return SavingsGoal::factory()->for($household, 'household')->create($attributes);
    }

    // ===== Acceso y CRUD =====

    public function test_invitado_es_redirigido_al_login(): void
    {
        $this->get(route('savings-goals.index'))->assertRedirect(route('login'));
    }

    public function test_los_formularios_de_alta_y_edicion_se_muestran(): void
    {
        [$owner, $household] = $this->setupHousehold();
        $goal = $this->goalFor($household);

        $this->actingAs($owner)->get(route('savings-goals.create'))->assertOk();
        $this->actingAs($owner)->get(route('savings-goals.edit', $goal))->assertOk();
    }

    public function test_se_crea_y_se_edita_una_meta(): void
    {
        [$owner, $household] = $this->setupHousehold();

        $response = $this->actingAs($owner)->post(route('savings-goals.store'), [
            'name' => 'Viaje a San Andrés',
            'target_amount' => '3500000',
            'target_date' => now()->addMonthsNoOverflow(6)->toDateString(),
            'priority' => 'medium',
            'monthly_commitment' => '300000',
            'notes' => null,
        ]);

        $goal = SavingsGoal::query()->where('name', 'Viaje a San Andrés')->firstOrFail();
        $response->assertRedirect(route('savings-goals.show', $goal));
        $this->assertSame('3500000.00', (string) $goal->target_amount);
        $this->assertSame('active', $goal->status->value);
    }

    public function test_la_edicion_actualiza_los_datos_de_la_meta(): void
    {
        [$owner, $household] = $this->setupHousehold();
        $goal = $this->goalFor($household, ['name' => 'Portátil', 'target_amount' => 3000000]);

        $this->actingAs($owner)->put(route('savings-goals.update', $goal), [
            'name' => 'Portátil nuevo',
            'target_amount' => '4000000',
            'priority' => 'high',
            'monthly_commitment' => '400000',
        ])->assertRedirect(route('savings-goals.show', $goal));

        $goal->refresh();
        $this->assertSame('Portátil nuevo', $goal->name);
        $this->assertSame('4000000.00', (string) $goal->target_amount);
        $this->assertSame('high', $goal->priority->value);
    }

    public function test_se_elimina_una_meta(): void
    {
        [$owner, $household] = $this->setupHousehold();
        $goal = $this->goalFor($household);

        $this->actingAs($owner)
            ->delete(route('savings-goals.destroy', $goal))
            ->assertRedirect(route('savings-goals.index'));

        $this->assertDatabaseMissing('savings_goals', ['id' => $goal->id]);
    }

    // ===== Aislamiento entre hogares =====

    public function test_otro_hogar_no_puede_ver_ni_editar_las_metas_ajenas(): void
    {
        [$ownerA, $householdA] = $this->setupHousehold('Hogar A');
        $goal = $this->goalFor($householdA, ['target_amount' => 1]);
        [$ownerB] = $this->setupHousehold('Hogar B');

        $this->actingAs($ownerB)->get(route('savings-goals.show', $goal))->assertForbidden();
        $this->actingAs($ownerB)->get(route('savings-goals.edit', $goal))->assertForbidden();
        $this->actingAs($ownerB)->put(route('savings-goals.update', $goal), [
            'name' => 'Hackeada',
            'target_amount' => '1',
        ])->assertForbidden();
        $this->actingAs($ownerB)->delete(route('savings-goals.destroy', $goal))->assertForbidden();
        $this->actingAs($ownerB)->post(route('savings-goals.contributions.store', $goal), [
            'amount' => '1000',
            'date' => now()->toDateString(),
            'type' => 'deposit',
        ])->assertForbidden();

        // Retiro sobre meta ajena: las reglas que dependen de la meta (saldo,
        // estado) no llegan a validar, así que no filtran datos en mensajes.
        $this->actingAs($ownerB)->post(route('savings-goals.contributions.store', $goal), [
            'amount' => '999999',
            'date' => now()->toDateString(),
            'type' => SavingsGoalContributionType::Withdrawal->value,
        ])->assertForbidden();

        $this->assertSame('1.00', (string) $goal->fresh()->target_amount);
    }

    public function test_no_se_puede_suplantar_el_hogar_al_crear(): void
    {
        [$ownerA, $householdA] = $this->setupHousehold('Hogar A');
        [, $householdB] = $this->setupHousehold('Hogar B');

        $this->actingAs($ownerA)->post(route('savings-goals.store'), [
            'name' => 'Meta suplantada',
            'target_amount' => '1000',
            // household_id no es fillable: debe ignorarse y usar el hogar activo.
            'household_id' => $householdB->id,
        ]);

        $goal = SavingsGoal::query()->where('name', 'Meta suplantada')->firstOrFail();
        $this->assertSame($householdA->id, $goal->household_id);
    }

    // ===== Aportes y retiros (ADR-0025) =====

    public function test_un_aporte_y_un_retiro_actualizan_lo_ahorrado(): void
    {
        [$owner, $household] = $this->setupHousehold();
        $goal = $this->goalFor($household, ['target_amount' => 1000000]);

        $this->actingAs($owner)->post(route('savings-goals.contributions.store', $goal), [
            'amount' => '400000',
            'date' => now()->subDay()->toDateString(),
            'type' => SavingsGoalContributionType::Deposit->value,
        ]);
        $this->actingAs($owner)->post(route('savings-goals.contributions.store', $goal), [
            'amount' => '150000',
            'date' => now()->toDateString(),
            'type' => SavingsGoalContributionType::Withdrawal->value,
        ]);

        $this->assertSame('250000.00', (string) $goal->fresh()->current_amount);
    }

    public function test_un_retiro_mayor_a_lo_ahorrado_es_rechazado(): void
    {
        [$owner, $household] = $this->setupHousehold();
        $goal = $this->goalFor($household, ['target_amount' => 1000000]);

        $this->actingAs($owner)->post(route('savings-goals.contributions.store', $goal), [
            'amount' => '150000',
            'date' => now()->toDateString(),
            'type' => SavingsGoalContributionType::Withdrawal->value,
        ])->assertSessionHasErrors('amount');

        $this->assertSame(0, $goal->contributions()->count());
    }

    public function test_al_alcanzar_la_meta_se_marca_lograda_y_se_reabre_si_se_borra_el_aporte(): void
    {
        [$owner, $household] = $this->setupHousehold();
        $goal = $this->goalFor($household, ['target_amount' => 500000]);

        $this->actingAs($owner)->post(route('savings-goals.contributions.store', $goal), [
            'amount' => '500000',
            'date' => now()->toDateString(),
            'type' => SavingsGoalContributionType::Deposit->value,
        ]);
        $this->assertSame(SavingsGoalStatus::Completed, $goal->fresh()->status);

        // Borrar el movimiento deshace el auto-completado.
        $contribution = $goal->contributions()->firstOrFail();
        $this->actingAs($owner)->delete(route('savings-goals.contributions.destroy', [$goal, $contribution]))
            ->assertRedirect();

        $goal->refresh();
        $this->assertSame('0.00', (string) $goal->current_amount);
        $this->assertSame(SavingsGoalStatus::Active, $goal->status);
    }

    public function test_las_metas_completadas_y_archivadas_no_aceptan_movimientos(): void
    {
        [$owner, $household] = $this->setupHousehold();
        $completed = $this->goalFor($household, ['status' => SavingsGoalStatus::Completed->value]);
        $archived = $this->goalFor($household, ['status' => SavingsGoalStatus::Archived->value]);

        foreach ([$completed, $archived] as $goal) {
            $this->actingAs($owner)->post(route('savings-goals.contributions.store', $goal), [
                'amount' => '1000',
                'date' => now()->toDateString(),
                'type' => SavingsGoalContributionType::Deposit->value,
            ])->assertSessionHasErrors('type');

            $this->assertSame(0, $goal->contributions()->count());
        }
    }

    // ===== Panel y dashboard =====

    public function test_el_panel_muestra_las_metas_del_hogar(): void
    {
        [$owner, $household] = $this->setupHousehold();
        $goal = $this->goalFor($household, ['name' => 'Viaje', 'target_amount' => 3000000]);

        $response = $this->actingAs($owner)->get(route('savings-goals.index'));

        $response->assertOk()->assertSee('Viaje');
    }

    public function test_el_filtro_de_estado_funciona(): void
    {
        [$owner, $household] = $this->setupHousehold();
        $this->goalFor($household, ['name' => 'Bicicleta']);
        $this->goalFor($household, ['name' => 'Xbox', 'status' => SavingsGoalStatus::Completed->value]);

        $this->actingAs($owner)->get(route('savings-goals.index', ['estado' => 'logradas']))
            ->assertOk()
            ->assertSee('Xbox')
            ->assertDontSee('Bicicleta');
    }

    public function test_pausar_quita_el_aporte_del_seam_de_ahorro(): void
    {
        [$owner, $household] = $this->setupHousehold();
        $goal = $this->goalFor($household, ['monthly_commitment' => 300000]);

        $this->actingAs($owner)->post(route('savings-goals.pause', $goal))->assertRedirect();

        $this->assertSame(0.0, app(SavingsGoalService::class)->committedMonthly($household->id));
        $this->assertSame(SavingsGoalStatus::Paused, $goal->fresh()->status);
    }

    public function test_el_dashboard_muestra_el_progreso_de_las_metas(): void
    {
        [$owner, $household] = $this->setupHousehold();
        $this->goalFor($household, ['name' => 'Portátil gamer', 'target_amount' => 3000000]);

        $this->actingAs($owner)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Metas de ahorro')
            ->assertSee('Portátil gamer');
    }
}
