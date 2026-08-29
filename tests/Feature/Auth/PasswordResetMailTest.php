<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El correo de recuperación de contraseña es uno de los dos únicos que
 * envía Finlia (ADR-0015). Usa la notificación nativa de Laravel, así que
 * su texto sale del framework: sin `lang/es.json` se renderiza en inglés.
 *
 * Estos tests renderizan el correo de verdad y comprueban que no queda
 * ni una cadena en inglés a la vista del usuario.
 */
class PasswordResetMailTest extends TestCase
{
    use RefreshDatabase;

    private function renderResetMail(): string
    {
        $user = User::factory()->create();

        return (new ResetPassword('token-de-prueba'))
            ->toMail($user)
            ->render()
            ->toHtml();
    }

    public function test_el_asunto_esta_en_espanol(): void
    {
        $user = User::factory()->create();

        $subject = (new ResetPassword('token-de-prueba'))->toMail($user)->subject;

        $this->assertSame('Recupera tu contraseña', $subject);
    }

    public function test_el_cuerpo_esta_en_espanol(): void
    {
        $html = $this->renderResetMail();

        $this->assertStringContainsString('Hola:', $html);
        $this->assertStringContainsString('Recibes este correo porque alguien pidió restablecer', $html);
        $this->assertStringContainsString('Restablecer contraseña', $html);
        $this->assertStringContainsString('Si no lo pediste tú', $html);
        $this->assertStringContainsString('Un saludo,', $html);
        $this->assertStringContainsString('Todos los derechos reservados.', $html);
    }

    public function test_la_caducidad_del_enlace_se_interpola_en_espanol(): void
    {
        $minutes = config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

        $this->assertStringContainsString(
            "Este enlace caduca en {$minutes} minutos.",
            $this->renderResetMail(),
        );
    }

    public function test_la_subcopia_del_boton_esta_traducida(): void
    {
        // Es la cadena más frágil: su clave lleva un salto de línea y comillas
        // escapadas. Si no coincide exactamente, sale en inglés sin avisar.
        $html = $this->renderResetMail();

        $this->assertStringContainsString('no funciona, copia y pega esta dirección', $html);
        $this->assertStringNotContainsString('having trouble clicking', $html);
    }

    public function test_no_queda_ninguna_cadena_en_ingles_a_la_vista(): void
    {
        $html = $this->renderResetMail();

        foreach ([
            'Reset your password',
            'You are receiving this email',
            'no further action is required',
            'Hello!',
            'Regards,',
            'All rights reserved.',
        ] as $english) {
            $this->assertStringNotContainsString($english, $html, "Quedó sin traducir: «{$english}».");
        }
    }
}
