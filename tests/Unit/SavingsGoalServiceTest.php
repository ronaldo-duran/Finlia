<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\SavingsGoalContributionType;
use App\Enums\SavingsGoalStatus;
use App\Models\Household;
use App\Models\SavingsGoal;
use App\Models\User;
use App\Services\HouseholdService;
use App\Services\SavingsGoalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Épica 7: cálculos de SavingsGoalService — aporte recomendado, seam
 * `savings` y toggles de estado.
 */
class SavingsGoalServiceTest extends TestCase
{
    use RefreshDatabase;

    private function setupHousehold(string $name = 'Hogar A'): array
    {
        $owner = User::factory()->create();
        $household = app(HouseholdService::class)->createHousehold($owner->id, $name);

        return [$owner, $household];
    }

    private function goalFor(Household $household, array $attributes = []): SavingsGoal
    {
        return SavingsGoal::factory()->for($household, 'household')->create($attributes);
    }

    private function service(): SavingsGoalService
    {
        return app(SavingsGoalService::class);
    }

    // ===== Aporte mensual recomendado =====

    public function test_el_aporte_recomendado_reparte_el_faltante_en_los_meses_que_quedan(): void
    {
        [$owner, $household] = $this->setupHousehold();
        $goal = $this->goalFor($household, [
            'target_amount' => 3000000,
            'target_date' => now(config('app.timezone'))->addMonthsNoOverflow(6)->toDateString(),
        ]);

        $r = $this->service()->recommendedMonthlyContribution($goal);

        $this->assertTrue($r['possible']);
        $this->assertSame(500000.0, $r['amount']);
    }

    public function test_sin_fecha_objetivo_no_hay_recomendacion(): void
    {
        [$owner, $household] = $this->setupHousehold();
        $goal = $this->goalFor($household, ['target_date' => null]);

        $r = $this->service()->recommendedMonthlyContribution($goal);

        $this->assertFalse($r['possible']);
        $this->assertNull($r['amount']);
    }

    public function test_fecha_objetivo_pasada_no_hay_recomendacion(): void
    {
        [$owner, $household] = $this->setupHousehold();
        $goal = $this->goalFor($household, ['target_date' => now()->subDay()->toDateString()]);

        $r = $this->service()->recommendedMonthlyContribution($goal);

        $this->assertFalse($r['possible']);
    }

    public function test_meta_cubierta_recomienda_cero(): void
    {
        [$owner, $household] = $this->setupHousehold();
        $goal = $this->goalFor($household, ['target_amount' => 1000000]);
        $this->service()->registerContribution($goal, [
            'amount' => 1200000,
            'date' => now()->toDateString(),
            'type' => SavingsGoalContributionType::Deposit->value,
        ]);

        $r = $this->service()->recommendedMonthlyContribution($goal, now());

        $this->assertSame(0.0, $r['amount']);
        $this->assertTrue($r['possible']);
    }

    // ===== Seam `savings` del dinero disponible =====

    public function test_el_seam_suma_el_aporte_mensual_de_las_metas_activas(): void
    {
        [$owner, $household] = $this->setupHousehold();
        $this->goalFor($household, ['monthly_commitment' => 300000, 'target_amount' => 10000000]);
        $this->goalFor($household, ['monthly_commitment' => 200000, 'target_amount' => 10000000]);

        $this->assertSame(500000.0, $this->service()->committedMonthly($household->id));
    }

    public function test_las_metas_pausadas_y_logradas_no_cuentan_en_el_seam(): void
    {
        [$owner, $household] = $this->setupHousehold();
        $this->goalFor($household, ['monthly_commitment' => 300000]);
        $this->goalFor($household, ['monthly_commitment' => 999999, 'status' => SavingsGoalStatus::Paused->value]);
        $this->goalFor($household, ['monthly_commitment' => 999999, 'status' => SavingsGoalStatus::Completed->value]);

        $this->assertSame(300000.0, $this->service()->committedMonthly($household->id));
    }

    public function test_el_seam_nunca_cuenta_mas_de_lo_que_falta(): void
    {
        [$owner, $household] = $this->setupHousehold();
        $goal = $this->goalFor($household, [
            'target_amount' => 1000000,
            'monthly_commitment' => 400000,
        ]);
        $this->service()->registerContribution($goal, [
            'amount' => 900000,
            'date' => now()->toDateString(),
            'type' => SavingsGoalContributionType::Deposit->value,
        ]);

        // Faltan 100.000: eso compromete, no los 400.000 programados.
        $this->assertSame(100000.0, $this->service()->committedMonthly($household->id));
    }

    // ===== Toggles de estado =====

    public function test_pausar_y_reactivar_cambian_el_estado(): void
    {
        [$owner, $household] = $this->setupHousehold();
        $goal = $this->goalFor($household);

        $this->service()->pause($goal);
        $this->assertSame(SavingsGoalStatus::Paused, $goal->fresh()->status);

        $this->service()->resume($goal);
        $this->assertSame(SavingsGoalStatus::Active, $goal->fresh()->status);
    }

    public function test_archivar_saca_la_meta_del_panel_vigente(): void
    {
        [$owner, $household] = $this->setupHousehold();
        $goal = $this->goalFor($household);

        $this->service()->archive($goal);

        $this->assertSame(SavingsGoalStatus::Archived, $goal->fresh()->status);
        $this->assertSame(
            0,
            SavingsGoal::where('household_id', $household->id)->outstanding()->count(),
        );
    }
}
