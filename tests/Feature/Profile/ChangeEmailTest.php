<?php

namespace Tests\Feature\Profile;

use App\Mail\ConfirmEmailChangeMail;
use App\Mail\EmailChangedNoticeMail;
use App\Models\User;
use App\Services\HouseholdService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Cambio de correo con doble confirmación (Plan 02, ADR-0030): nada entra
 * a users.email sin pasar por la bandeja nueva. Token aleatorio (hash
 * sha256 en la base), 60 min de vigencia, aviso al correo anterior.
 */
class ChangeEmailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // phpunit usa MAIL_MAILER=array (transport falso, ADR-0015): el
        // envío se salta con él. Los tests de correo declaran un transporte
        // real y Mail::fake() hace de SMTP.
        config(['mail.default' => 'smtp']);
    }

    /**
     * Pide el cambio y devuelve el token público del correo capturado.
     */
    private function requestChange(User $user, string $newEmail): string
    {
        // from() fija la URL previa: el controlador responde back() a /perfil.
        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->put(route('profile.email.update'), ['email' => $newEmail])
            ->assertRedirect(route('profile.edit'));

        $confirmUrl = null;
        Mail::assertSent(ConfirmEmailChangeMail::class, function (ConfirmEmailChangeMail $mail) use (&$confirmUrl, $newEmail) {
            $confirmUrl = $mail->confirmationUrl;

            return $mail->hasTo($newEmail);
        });

        return (string) basename((string) $confirmUrl);
    }

    // ---- Solicitud ----

    public function test_pide_el_cambio_y_el_correo_queda_pendiente(): void
    {
        Mail::fake();
        $user = User::factory()->create(['email' => 'viejo@ejemplo.com']);
        $verificadoAntes = $user->email_verified_at;

        $token = $this->requestChange($user, 'nuevo@ejemplo.com');

        $this->assertSame(64, strlen($token));

        $fresh = $user->fresh();
        $this->assertSame('viejo@ejemplo.com', $fresh->email); // no cambia aún
        $this->assertSame('nuevo@ejemplo.com', $fresh->pending_email);
        $this->assertNotNull($fresh->pending_email_token);
        $this->assertNotSame($token, $fresh->pending_email_token); // hash, no el token público
        $this->assertSame($verificadoAntes->toDateTimeString(), $fresh->email_verified_at->toDateTimeString());
    }

    public function test_rechaza_un_correo_verificado_de_otro_usuario(): void
    {
        Mail::fake();
        $user = User::factory()->create(['email' => 'ana@ejemplo.com']);
        User::factory()->create(['email' => 'tomado@ejemplo.com']);

        $this->actingAs($user)
            ->put(route('profile.email.update'), ['email' => 'tomado@ejemplo.com'])
            ->assertSessionHasErrors('email');

        $this->assertNull($user->fresh()->pending_email);
        Mail::assertNothingSent();
    }

    public function test_rechaza_un_correo_pendiente_de_otro_usuario(): void
    {
        Mail::fake();
        $ana = User::factory()->create(['email' => 'ana@ejemplo.com']);
        $beto = User::factory()->create(['email' => 'beto@ejemplo.com']);

        $this->requestChange($beto, 'codiciado@ejemplo.com');

        $this->actingAs($ana)
            ->put(route('profile.email.update'), ['email' => 'codiciado@ejemplo.com'])
            ->assertSessionHasErrors('email');

        $this->assertNull($ana->fresh()->pending_email);
    }

    public function test_rechaza_el_propio_correo_actual(): void
    {
        Mail::fake();
        $user = User::factory()->create(['email' => 'actual@ejemplo.com']);

        $this->actingAs($user)
            ->put(route('profile.email.update'), ['email' => 'actual@ejemplo.com'])
            ->assertSessionHasErrors('email');

        $this->assertNull($user->fresh()->pending_email);
        Mail::assertNothingSent();
    }

    public function test_repetir_el_propio_pendiente_regenera_el_enlace(): void
    {
        Mail::fake();
        $user = User::factory()->create(['email' => 'viejo@ejemplo.com']);

        $primerToken = $this->requestChange($user, 'nuevo@ejemplo.com');
        $primerHash = $user->fresh()->pending_email_token;

        $segundoToken = $this->requestChange($user, 'nuevo@ejemplo.com');

        $this->assertNotSame($primerToken, $segundoToken);

        // Solo el último enlace queda vivo: el hash del primero ya no está.
        $this->assertNotSame($primerHash, $user->fresh()->pending_email_token);
        Mail::assertSent(ConfirmEmailChangeMail::class, 2);
    }

    // ---- Confirmación ----

    public function test_token_valido_confirma_cambia_y_verifica(): void
    {
        Mail::fake();
        $user = User::factory()->create(['email' => 'viejo@ejemplo.com']);
        $token = $this->requestChange($user, 'nuevo@ejemplo.com');

        $this->actingAs($user)
            ->get(route('profile.email.confirm', ['token' => $token]))
            ->assertRedirect(route('profile.edit'));

        $fresh = $user->fresh();
        $this->assertSame('nuevo@ejemplo.com', $fresh->email);
        $this->assertNotNull($fresh->email_verified_at); // verificado por construcción
        $this->assertNull($fresh->pending_email);
        $this->assertNull($fresh->pending_email_token);
        $this->assertNull($fresh->pending_email_requested_at);

        // La pierna antifraude: aviso a la bandeja ANTIGUA.
        Mail::assertSent(EmailChangedNoticeMail::class, fn ($mail) => $mail->hasTo('viejo@ejemplo.com'));
    }

    public function test_confirma_sin_sesion_y_vuelve_al_login(): void
    {
        Mail::fake();
        $user = User::factory()->create(['email' => 'viejo@ejemplo.com']);
        $token = $this->requestChange($user, 'nuevo@ejemplo.com');

        // Click desde la bandeja nueva en otro navegador: sin sesión.
        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();

        $this->get(route('profile.email.confirm', ['token' => $token]))
            ->assertRedirect(route('login'));

        $this->assertSame('nuevo@ejemplo.com', $user->fresh()->email);
    }

    public function test_token_invalido_muestra_error_y_no_cambia_nada(): void
    {
        Mail::fake();
        $user = User::factory()->create(['email' => 'viejo@ejemplo.com']);
        $this->requestChange($user, 'nuevo@ejemplo.com');

        $this->get(route('profile.email.confirm', ['token' => str_repeat('x', 64)]))
            ->assertOk()
            ->assertSee('El enlace no funcionó');

        $fresh = $user->fresh();
        $this->assertSame('viejo@ejemplo.com', $fresh->email);
        $this->assertNotNull($fresh->pending_email); // el pendiente sigue vivo
        Mail::assertNotSent(EmailChangedNoticeMail::class);
    }

    public function test_token_expirado_rechaza_y_limpia_el_pendiente(): void
    {
        Mail::fake();
        $user = User::factory()->create(['email' => 'viejo@ejemplo.com']);
        $token = $this->requestChange($user, 'nuevo@ejemplo.com');

        $this->travel(61)->minutes();

        $this->get(route('profile.email.confirm', ['token' => $token]))
            ->assertOk()
            ->assertSee('expiró');

        $fresh = $user->fresh();
        $this->assertSame('viejo@ejemplo.com', $fresh->email);
        $this->assertNull($fresh->pending_email); // vencido = limpio
        Mail::assertNotSent(EmailChangedNoticeMail::class);
    }

    public function test_conflicto_con_cuenta_verificada_rechaza(): void
    {
        Mail::fake();
        $user = User::factory()->create(['email' => 'viejo@ejemplo.com']);
        $token = $this->requestChange($user, 'codiciado@ejemplo.com');

        // Carrera: otra cuenta verificó ese correo mientras el enlace viajaba.
        User::factory()->create(['email' => 'codiciado@ejemplo.com']);

        $this->get(route('profile.email.confirm', ['token' => $token]))
            ->assertOk()
            ->assertSee('otra cuenta');

        $fresh = $user->fresh();
        $this->assertSame('viejo@ejemplo.com', $fresh->email);
        $this->assertNull($fresh->pending_email);
    }

    public function test_confirma_reclamando_un_fantasma_sin_verificar(): void
    {
        Mail::fake();
        $user = User::factory()->create(['email' => 'viejo@ejemplo.com']);
        $token = $this->requestChange($user, 'fantasma@ejemplo.com');

        // Fantasma (Plan 01): registro sin verificar con ese correo — inerte
        // por construcción. Confirmar el enlace prueba la bandeja mejor que
        // el registro probó nada: el fantasma se reclama.
        $ghost = User::factory()->unverified()->create(['email' => 'fantasma@ejemplo.com']);
        $ghostHousehold = app(HouseholdService::class)->createHousehold($ghost->id, 'Mi hogar');

        $this->get(route('profile.email.confirm', ['token' => $token]))
            ->assertRedirect();

        $this->assertSame('fantasma@ejemplo.com', $user->fresh()->email);
        $this->assertDatabaseMissing('users', ['id' => $ghost->id]);
        $this->assertDatabaseMissing('households', ['id' => $ghostHousehold->id]);
    }

    public function test_el_cambio_no_toca_hogares_ni_preferencias(): void
    {
        Mail::fake();
        $user = User::factory()->create(['email' => 'viejo@ejemplo.com']);
        $household = app(HouseholdService::class)->createHousehold($user->id, 'Mi hogar');

        // Opt-in del digest (Épica 9): la preferencia es del pivote, no del
        // correo — debe sobrevivir al cambio de dirección.
        $household->members()->updateExistingPivot($user->id, ['reminders_email' => true]);

        $token = $this->requestChange($user, 'nuevo@ejemplo.com');
        $this->get(route('profile.email.confirm', ['token' => $token]));

        $this->assertTrue(
            $user->fresh()->households()->where('households.id', $household->id)->first()->pivot->reminders_email,
        );
    }

    // ---- Contenido de los correos ----

    public function test_correo_de_confirmacion_renderiza_en_espanol(): void
    {
        $mail = new ConfirmEmailChangeMail(
            'María González',
            'nuevo@ejemplo.com',
            'https://finlia.test/confirmar-correo/abc123',
            now()->addMinutes(60),
        );

        $mail->assertSeeInHtml('Confirma tu nuevo correo');
        $mail->assertSeeInHtml('nuevo@ejemplo.com');
        $mail->assertSeeInHtml('Si no pediste este cambio');
        $mail->assertSeeInText('Confirma tu nuevo correo');
    }

    public function test_aviso_al_correo_antiguo_renderiza_en_espanol(): void
    {
        $mail = new EmailChangedNoticeMail(
            'María González',
            'viejo@ejemplo.com',
            'nuevo@ejemplo.com',
            'https://finlia.test/recuperar-contrasena',
        );

        $mail->assertSeeInHtml('Tu cuenta cambió de correo');
        $mail->assertSeeInHtml('viejo@ejemplo.com');
        $mail->assertSeeInHtml('nuevo@ejemplo.com');
        $mail->assertSeeInText('¿No fuiste tú?');
    }
}
