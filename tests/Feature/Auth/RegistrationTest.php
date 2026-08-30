<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\HouseholdService;
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
        // Plan 01: no entra al dashboard; va al aviso "revisa tu correo"
        // y queda sin verificar hasta confirmar.
        $response->assertRedirect(route('verification.notice'));

        $this->assertDatabaseHas('users', [
            'email' => 'maria@ejemplo.com',
            'name' => 'María González',
            'email_verified_at' => null,
        ]);
    }

    public function test_no_se_puede_registrar_un_correo_duplicado(): void
    {
        // Solo un correo VERIFICADO cuenta como tomado (anti-squatting).
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

    public function test_registro_reclama_un_correo_registrado_sin_verificar(): void
    {
        // Anti-squatting (Plan 01, regla 6): un fantasma sin verificar no
        // puede bloquear al dueño real del correo.
        $ghost = User::factory()->unverified()->create(['email' => 'fantasma@ejemplo.com']);
        $ghostHousehold = app(HouseholdService::class)
            ->createHousehold($ghost->id, 'Mi hogar');

        $response = $this->post(route('register'), [
            'name' => 'Dueño Real',
            'email' => 'fantasma@ejemplo.com',
            'password' => 'secreto123',
            'password_confirmation' => 'secreto123',
        ]);

        // El dueño real no ve ningún error: flujo normal de registro.
        $response->assertRedirect(route('verification.notice'));
        $this->assertAuthenticated();

        $newUser = User::firstWhere('email', 'fantasma@ejemplo.com');
        $this->assertNotSame($ghost->id, $newUser->id);
        $this->assertNull($newUser->email_verified_at);

        // El fantasma y su hogar vacío desaparecen; el nuevo usuario tiene
        // su propio hogar.
        $this->assertDatabaseMissing('users', ['id' => $ghost->id]);
        $this->assertDatabaseMissing('households', ['id' => $ghostHousehold->id]);
        $this->assertTrue($newUser->households()->exists());
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
