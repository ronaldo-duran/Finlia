<?php

namespace Tests\Feature;

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

    public function test_dashboard_muestra_kpis_y_boton_registrar_gasto(): void
    {
        $user = User::factory()->create();
        app(HouseholdService::class)->createHousehold($user->id, 'Mi hogar');

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Registrar gasto');
        $response->assertSee('Ingresos del mes');
        $response->assertSee('Gastos del mes');
    }

    public function test_raiz_redirige_a_login_si_invitado(): void
    {
        $response = $this->get(route('home'));

        $response->assertRedirect(route('login'));
    }

    public function test_raiz_redirige_a_dashboard_si_autenticado(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('home'));

        $response->assertRedirect(route('dashboard'));
    }
}
