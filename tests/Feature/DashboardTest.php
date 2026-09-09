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

    public function test_dashboard_muestra_kpis_y_acceso_a_registrar(): void
    {
        $user = User::factory()->create();
        app(HouseholdService::class)->createHousehold($user->id, 'Mi hogar');

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        // El panel ya no lleva botones propios de "Gasto"/"Ingreso": duplicaban
        // dos de las cinco acciones del "+" flotante, que ahora es la única
        // entrada para registrar.
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
}
