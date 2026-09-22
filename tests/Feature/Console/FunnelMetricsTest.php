<?php

namespace Tests\Feature\Console;

use App\Models\Account;
use App\Models\Expense;
use App\Models\Household;
use App\Models\User;
use App\Services\HouseholdService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Métricas del embudo (plan de lanzamiento T5).
 */
class FunnelMetricsTest extends TestCase
{
    use RefreshDatabase;

    public function test_calcula_el_embudo_sin_contar_las_cuentas_purgadas(): void
    {
        $ana = User::factory()->create(['created_at' => now()->subDays(10)]);
        $hogarDeAna = $this->gasto($ana, '2026-09-01', now()->subDays(10));
        $this->gasto($ana, '2026-09-02', now()->subDays(2));

        $beto = User::factory()->create(['email' => 'deleted+99@finlia.invalid']);
        $this->gasto($beto, '2026-09-03', now()->subDays(5), $hogarDeAna);
        $this->gasto($beto, '2026-09-04', now()->subDays(4), $hogarDeAna);

        User::factory()->unverified()->create(['created_at' => now()->subDays(10)]);

        $carla = User::factory()->create(['created_at' => now()->subDay()]);
        $this->gasto($carla, '2026-09-13', now());
        $this->gasto($carla, '2026-09-13', now());

        User::factory()->create(['deletion_requested_at' => now()]);

        $this->artisan('finlia:metrics')
            ->expectsTable(['Métrica', 'Valor'], [
                ['Registros', 4],
                ['Verificados', '3 (75 %)'],
                ['Hogares con al menos un movimiento', 2],
                ['Usuarios con gastos en 2 días distintos', 1],
                ['Activos 7 días después de registrarse', '1 de 2'],
                ['Eliminaciones pedidas (en suspensión)', 1],
                ['Cuentas ya eliminadas', 1],
            ])
            ->assertSuccessful();
    }

    private function gasto(User $user, string $fecha, \DateTimeInterface $creado, ?Household $hogar = null): Household
    {
        $hogar ??= $user->households()->first()
            ?? app(HouseholdService::class)->createHousehold($user->id, 'Hogar de '.$user->name);

        Expense::factory()->create([
            'household_id' => $hogar->id,
            'user_id' => $user->id,
            'account_id' => Account::factory()->create(['household_id' => $hogar->id])->id,
            'date' => $fecha,
            'created_at' => $creado,
        ]);

        return $hogar;
    }
}
