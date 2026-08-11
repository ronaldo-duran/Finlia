<?php

namespace Tests\Feature;

use App\Models\User;
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

    public function test_usuario_autenticado_ve_el_dashboard(): void
    {
        $user = User::factory()->create(['name' => 'Ronaldo Tester']);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Hola, Ronaldo Tester');
    }

    public function test_dashboard_muestra_estado_inicial(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Disponible para gastar');
        $response->assertSee('Tu panel está listo');
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
