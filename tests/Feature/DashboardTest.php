<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Enums\SavingsGoalStatus;
use App\Models\Account;
use App\Models\SavingsGoal;
use App\Models\User;
use App\Services\HouseholdService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_es_redirigido_al_login(): void
    {
        $response = $this->get(route('dashboard'));

        $response->assertRedirect(route('login'));
    }

    public function test_usuario_sin_hogar_es_redirigido_a_crear_hogar(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('households.create'));
    }

    public function test_usuario_autenticado_ve_el_dashboard(): void
    {
        $user = User::factory()->create(['name' => 'Ronaldo Tester']);
        app(HouseholdService::class)->createHousehold($user->id, 'Mi hogar');

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Hola, Ronaldo Tester');
    }

    public function test_dashboard_muestra_kpis_y_acceso_a_registrar(): void
    {
        $user = User::factory()->create();
        app(HouseholdService::class)->createHousehold($user->id, 'Mi hogar');

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Registrar movimiento');
        $response->assertSee('Ingresos del mes');
        $response->assertSee('Gastos del mes');
    }

    /**
     * La raíz dejó de redirigir: ahora es la landing pública.
     *
     * Sin dominios configurados —el caso de los tests y del desarrollo local—
     * sitio y aplicación comparten host, así que «/» es el sitio público para
     * todo el mundo, con sesión o sin ella. El reparto en dos hosts se
     * verifica en Tests\Feature\Marketing\DomainRoutingTest.
     */
    public function test_la_raiz_muestra_la_landing_publica(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('¿Cuánto puedes gastar hoy', false);
    }

    public function test_la_landing_tambien_responde_con_sesion_iniciada(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('home'))
            ->assertOk()
            ->assertSee('¿Cuánto puedes gastar hoy', false);
    }

    /**
     * Con mucho apartado en metas y poco saldo libre, el hero cae en
     * `status = short` y tiene que enseñar el desglose (saldo, apartado en
     * metas, compromisos) para que el faltante no parezca sacado de la manga.
     */
    public function test_el_hero_muestra_el_desglose_cuando_falta_plata(): void
    {
        $user = User::factory()->create();
        $household = app(HouseholdService::class)->createHousehold($user->id, 'Mi hogar');

        Account::factory()->create([
            'household_id' => $household->id,
            'type' => AccountType::Bank->value,
            'initial_balance' => 100000,
            'current_balance' => 100000,
            'is_active' => true,
        ]);
        SavingsGoal::factory()->create([
            'household_id' => $household->id,
            'name' => 'Fondo de emergencia',
            'target_amount' => 5000000,
            'current_amount' => 500000,
            'status' => SavingsGoalStatus::Active->value,
            'monthly_commitment' => null,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Te falta plata antes de tu próximo pago');
        $response->assertSee('data-testid="liquidity-breakdown"', false);
        $response->assertSee('Saldo en cuentas');
        $response->assertSee('Apartado en metas');
        $response->assertSee('Te faltan');
    }
}
