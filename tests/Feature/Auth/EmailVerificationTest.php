<?php

namespace Tests\Feature\Auth;

use App\Mail\VerifyEmailMail;
use App\Models\User;
use App\Services\HouseholdService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Verificación de correo obligatoria en el registro (Plan 01, ADR-0029):
 * la cuenta se crea completa pero la app queda bloqueada hasta confirmar
 * el correo; el enlace es público + firmado y el reenvío tiene throttle.
 */
class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // phpunit usa MAIL_MAILER=array (transport "falso", ADR-0015): el
        // envío se salta con él. Los tests de correo declaran un transporte
        // real y Mail::fake() hace de SMTP.
        config(['mail.default' => 'smtp']);
    }

    private function signedUrl(User $user): string
    {
        return URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->getKey(),
            'hash' => sha1($user->email),
        ]);
    }

    // ---- Registro ----

    public function test_registro_envia_el_correo_de_verificacion(): void
    {
        Mail::fake();

        $response = $this->post(route('register'), [
            'name' => 'María González',
            'email' => 'maria@ejemplo.com',
            'password' => 'secreto123',
            'password_confirmation' => 'secreto123',
            'birth_date' => '1990-05-12',
        ]);

        $response->assertRedirect(route('verification.notice'));

        Mail::assertSent(VerifyEmailMail::class, fn ($mail) => $mail->hasTo('maria@ejemplo.com'));
    }

    // ---- Bloqueo hasta verificar ----

    public function test_usuario_sin_verificar_es_redirigido_al_aviso(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('verification.notice'));

        $this->actingAs($user)
            ->get(route('movements.index'))
            ->assertRedirect(route('verification.notice'));
    }

    public function test_usuario_sin_verificar_puede_cerrar_sesion(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_usuario_verificado_navega_normal(): void
    {
        $user = User::factory()->create();
        app(HouseholdService::class)->createHousehold($user->id, 'Mi hogar');

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk();
    }

    // ---- Enlace firmado ----

    public function test_enlace_valido_confirma_y_deja_entrar(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->get($this->signedUrl($user))
            ->assertRedirect(route('dashboard'));

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_enlace_valido_funciona_sin_sesion(): void
    {
        // Click desde el buzón en otro dispositivo/navegador.
        $user = User::factory()->unverified()->create();

        $this->get($this->signedUrl($user))
            ->assertRedirect(route('login'));

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_enlace_sin_firma_valida_rechaza(): void
    {
        $user = User::factory()->unverified()->create();

        // URL a mano: firma ausente (el middleware 'signed' responde 403).
        $this->get("/verificar-correo/{$user->id}/".sha1($user->email))
            ->assertForbidden();

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_enlace_expirado_rechaza(): void
    {
        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute('verification.verify', now()->subMinute(), [
            'id' => $user->getKey(),
            'hash' => sha1($user->email),
        ]);

        $this->get($url)->assertForbidden();

        $this->assertNull($user->fresh()->email_verified_at);
    }

    // ---- Reenvío ----

    public function test_reenvio_vuelve_a_enviar_el_enlace(): void
    {
        Mail::fake();
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->post(route('verification.send'))
            ->assertRedirect();

        Mail::assertSent(VerifyEmailMail::class, fn ($mail) => $mail->hasTo($user->email));
    }

    public function test_reenvio_tiene_throttle_por_usuario(): void
    {
        Mail::fake();
        $user = User::factory()->unverified()->create();

        for ($i = 0; $i < 3; $i++) {
            $this->actingAs($user)
                ->post(route('verification.send'))
                ->assertRedirect();
        }

        // 4.º dentro del mismo minuto: 429 (límite 3/min por usuario).
        $this->actingAs($user)
            ->post(route('verification.send'))
            ->assertStatus(429);
    }

    public function test_reenvio_si_ya_esta_verificado_redirige_al_dashboard(): void
    {
        Mail::fake();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('verification.send'))
            ->assertRedirect(route('dashboard'));

        Mail::assertNothingSent();
    }

    // ---- Contenido del correo ----

    public function test_correo_renderiza_en_espanol(): void
    {
        $user = User::factory()->make(['name' => 'María González']);

        $mail = new VerifyEmailMail(
            $user,
            'https://finlia.test/verificar-correo/1/abc123',
            now()->addMinutes(60),
        );

        $mail->assertSeeInHtml('Confirma que este correo es tuyo');
        $mail->assertSeeInHtml('María González');
        $mail->assertSeeInText('Confirma tu correo');
    }
}
