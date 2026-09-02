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
            'birth_date' => '1990-05-12',
        ]);

        $this->assertAuthenticated();
        // Plan 01: no entra al dashboard; va al aviso "revisa tu correo"
        // y queda sin verificar hasta confirmar.
        $response->assertRedirect(route('verification.notice'));

        $this->assertDatabaseHas('users', [
            'email' => 'maria@ejemplo.com',
            'name' => 'María González',
            'birth_date' => '1990-05-12',
            'email_verified_at' => null,
        ]);

        // Todo usuario arranca con su hogar personal activo (Épica 2) —
        // la aserción vive aquí desde el plan 01: el e2e ya no llega al
        // dashboard para ver el selector de hogares.
        $user = User::firstWhere('email', 'maria@ejemplo.com');
        $this->assertTrue($user->households()->where('name', 'Mi hogar')->exists());
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
            'birth_date' => '1990-05-12',
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
            'birth_date' => '1990-05-12',
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

    // ---- Fecha de nacimiento (Plan 04: obligatoria y 18+) ----

    public function test_la_fecha_de_nacimiento_es_obligatoria(): void
    {
        $this->post(route('register'), [
            'name' => 'Usuario Prueba',
            'email' => 'sin-fecha@ejemplo.com',
            'password' => 'secreto123',
            'password_confirmation' => 'secreto123',
            'birth_date' => '',
        ])->assertSessionHasErrors('birth_date');

        $this->assertGuest();
    }

    public function test_un_menor_de_edad_no_puede_registrarse(): void
    {
        // 17 años cumplidos: la recomendación del plan 04 es 18+ (ADR-0032).
        // El mensaje debe decir POR QUÉ, no un "valor inválido" genérico.
        $this->post(route('register'), [
            'name' => 'Menor de Edad',
            'email' => 'menor@ejemplo.com',
            'password' => 'secreto123',
            'password_confirmation' => 'secreto123',
            'birth_date' => today()->subYears(17)->toDateString(),
        ])->assertInvalid(['birth_date' => 'mayor de edad']);

        $this->assertDatabaseMissing('users', ['email' => 'menor@ejemplo.com']);
    }

    public function test_quien_cumple_18_hoy_puede_registrarse(): void
    {
        // Corte inclusivo: la mayoría de edad se alcanza el día del cumpleaños.
        $this->post(route('register'), [
            'name' => 'Justo Mayor',
            'email' => 'justo18@ejemplo.com',
            'password' => 'secreto123',
            'password_confirmation' => 'secreto123',
            'birth_date' => today()->subYears(18)->toDateString(),
        ])->assertRedirect(route('verification.notice'));

        $this->assertDatabaseHas('users', ['email' => 'justo18@ejemplo.com']);
    }

    public function test_una_fecha_futura_se_rechaza(): void
    {
        $this->post(route('register'), [
            'name' => 'Viajero del Tiempo',
            'email' => 'futuro@ejemplo.com',
            'password' => 'secreto123',
            'password_confirmation' => 'secreto123',
            'birth_date' => today()->addYear()->toDateString(),
        ])->assertSessionHasErrors('birth_date');

        $this->assertDatabaseMissing('users', ['email' => 'futuro@ejemplo.com']);
    }

    public function test_una_fecha_anterior_a_1900_se_rechaza(): void
    {
        $this->post(route('register'), [
            'name' => 'Matusalén',
            'email' => 'matusalen@ejemplo.com',
            'password' => 'secreto123',
            'password_confirmation' => 'secreto123',
            'birth_date' => '1850-01-01',
        ])->assertSessionHasErrors('birth_date');
    }

    public function test_el_registro_no_pide_region_ni_genero(): void
    {
        // Menos fricción de entrada (Plan 04): esos campos viven en /perfil.
        $this->get(route('register'))
            ->assertOk()
            ->assertSee('Fecha de nacimiento')
            ->assertDontSee('Región')
            ->assertDontSee('Género');
    }

    public function test_usuario_autenticado_es_redirigido_del_registro(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('register'));

        $response->assertRedirect();
    }
}
