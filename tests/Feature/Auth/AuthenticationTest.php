<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_muestra_el_formulario(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertSee('Iniciar sesión');
    }

    public function test_usuario_puede_iniciar_sesion_con_credenciales_validas(): void
    {
        $user = User::factory()->create([
            'password' => 'finlia123',
        ]);

        $response = $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'finlia123',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('dashboard'));
    }

    public function test_no_se_puede_iniciar_sesion_con_contrasena_incorrecta(): void
    {
        $user = User::factory()->create([
            'password' => 'finlia123',
        ]);

        $response = $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'contraseña-incorrecta',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_usuario_puede_cerrar_sesion(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('logout'));

        $this->assertGuest();
        $response->assertRedirect(route('login'));
    }

    public function test_usuario_autenticado_es_redirigido_del_login(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('login'));

        $response->assertRedirect();
    }

    public function test_email_es_obligatorio_para_iniciar_sesion(): void
    {
        $response = $this->post(route('login'), [
            'email' => '',
            'password' => 'finlia123',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }
}
