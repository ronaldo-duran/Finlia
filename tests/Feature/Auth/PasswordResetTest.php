<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_pantalla_de_recuperacion_se_muestra(): void
    {
        $response = $this->get(route('password.request'));

        $response->assertOk();
        $response->assertSee('Recuperar contraseña');
    }

    public function test_solicitar_enlace_envia_notificacion_a_usuario_existente(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $response = $this->post(route('password.email'), [
            'email' => $user->email,
        ]);

        $response->assertSessionHas('status');
        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_solicitar_enlace_no_revela_correos_inexistentes(): void
    {
        Notification::fake();

        $response = $this->post(route('password.email'), [
            'email' => 'inexistente@ejemplo.com',
        ]);

        // Mismo mensaje de éxito (no revela que el correo no existe).
        $response->assertSessionHas('status', __('passwords.sent'));
    }

    public function test_pantalla_de_restablecer_se_muestra_con_token(): void
    {
        $response = $this->get(route('password.reset', ['token' => 'token-de-prueba']));

        $response->assertOk();
        $response->assertSee('Nueva contraseña');
    }

    public function test_usuario_puede_restablecer_contrasena_con_token_valido(): void
    {
        $user = User::factory()->create([
            'password' => 'clave-anterior',
        ]);

        $token = Password::createToken($user);

        $response = $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'nuevaClave456',
            'password_confirmation' => 'nuevaClave456',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('status');

        // La contraseña nueva debe funcionar; la vieja, no.
        $this->assertTrue(Hash::check('nuevaClave456', $user->fresh()->password));
        $this->assertFalse(Hash::check('clave-anterior', $user->fresh()->password));
    }

    public function test_no_se_puede_restablecer_con_token_invalido(): void
    {
        $user = User::factory()->create();

        $response = $this->post(route('password.update'), [
            'token' => 'token-invalido',
            'email' => $user->email,
            'password' => 'nuevaClave456',
            'password_confirmation' => 'nuevaClave456',
        ]);

        $response->assertSessionHasErrors('email');
    }
}
