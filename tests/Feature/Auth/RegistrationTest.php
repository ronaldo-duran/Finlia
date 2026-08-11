<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registro_muestra_el_formulario(): void
    {
        $response = $this->get(route('register'));

        $response->assertOk();
        $response->assertSee('Crear cuenta');
    }

    public function test_usuario_puede_registrarse_con_datos_validos(): void
    {
        $response = $this->post(route('register'), [
            'name' => 'María González',
            'email' => 'maria@ejemplo.com',
            'password' => 'secreto123',
            'password_confirmation' => 'secreto123',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('users', [
            'email' => 'maria@ejemplo.com',
            'name' => 'María González',
        ]);
    }

    public function test_no_se_puede_registrar_un_correo_duplicado(): void
    {
        User::factory()->create(['email' => 'repetido@ejemplo.com']);

        $response = $this->post(route('register'), [
            'name' => 'Otro Usuario',
            'email' => 'repetido@ejemplo.com',
            'password' => 'secreto123',
            'password_confirmation' => 'secreto123',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_las_contrasenas_deben_coincidir(): void
    {
        $response = $this->post(route('register'), [
            'name' => 'Usuario Prueba',
            'email' => 'nuevo@ejemplo.com',
            'password' => 'secreto123',
            'password_confirmation' => 'otraCosa456',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertGuest();
    }

    public function test_contrasena_debe_tener_al_menos_8_caracteres(): void
    {
        $response = $this->post(route('register'), [
            'name' => 'Usuario Prueba',
            'email' => 'nuevo2@ejemplo.com',
            'password' => 'corta',
            'password_confirmation' => 'corta',
        ]);

        $response->assertSessionHasErrors('password');
    }

    public function test_usuario_autenticado_es_redirigido_del_registro(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('register'));

        $response->assertRedirect();
    }
}
