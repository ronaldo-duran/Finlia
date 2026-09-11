<?php

namespace Tests\Feature\Household;

use App\Enums\HouseholdRole;
use App\Mail\VerifyEmailMail;
use App\Models\Account;
use App\Models\Household;
use App\Models\User;
use App\Services\HouseholdService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * La invitación funciona desde el buzón, con o sin cuenta (ADR-0039).
 *
 * El enlace llega casi siempre a alguien sin sesión —y a menudo reenviado por
 * WhatsApp—, así que verlo es público; aceptarlo exige correo verificado. El
 * registro desde una invitación no crea hogar propio: la persona entra al
 * hogar invitado al confirmar su correo, que es cuando queda probado que la
 * dirección es suya.
 */
class InvitationOnboardingTest extends TestCase
{
    use RefreshDatabase;

    private const INVITED = 'vanessa@finlia.test';

    protected function setUp(): void
    {
        parent::setUp();

        // phpunit usa MAIL_MAILER=array, que se salta el envío (ADR-0015).
        config(['mail.default' => 'smtp']);
        Mail::fake();
    }

    /**
     * @return array{0: Household, 1: string}
     */
    private function invite(string $email = self::INVITED): array
    {
        $owner = User::factory()->create();
        $service = app(HouseholdService::class);
        $household = $service->createHousehold($owner->id, 'Hogar Durán');

        [, $plainToken] = $service->inviteMember($household, $email, HouseholdRole::Member);

        return [$household, $plainToken];
    }

    /**
     * @param  array<string, string>  $overrides
     */
    private function register(array $overrides = []): void
    {
        $this->post(route('register'), array_merge([
            'name' => 'Vanessa',
            'email' => self::INVITED,
            'password' => 'secreto123',
            'password_confirmation' => 'secreto123',
            'birth_date' => '1992-03-08',
        ], $overrides));
    }

    private function verificationUrl(User $user): string
    {
        return URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->getKey(),
            'hash' => sha1($user->email),
        ]);
    }

    // ---- Ver la invitación sin sesión ----

    public function test_la_invitacion_se_ve_sin_sesion(): void
    {
        [, $token] = $this->invite();

        $this->get(route('invitations.show', $token))
            ->assertOk()
            ->assertSee('Hogar Durán')
            ->assertSee('Crear mi cuenta')
            ->assertSee('Ya tengo cuenta, entrar');

        $this->assertGuest();
    }

    public function test_sin_sesion_la_invitacion_se_recuerda_para_el_paso_siguiente(): void
    {
        [, $token] = $this->invite();

        $this->get(route('invitations.show', $token))
            ->assertSessionHas('invitation_token', $token)
            ->assertSessionHas('url.intended', route('invitations.show', $token));
    }

    public function test_la_pagina_publica_no_muestra_el_dinero_del_hogar(): void
    {
        [$household, $token] = $this->invite();

        Account::factory()->create([
            'household_id' => $household->id,
            'name' => 'Cuenta de nómina reservada',
            'initial_balance' => 7654321,
        ]);

        $this->get(route('invitations.show', $token))
            ->assertOk()
            ->assertDontSee('Cuenta de nómina reservada')
            ->assertDontSee('7.654.321');
    }

    public function test_la_pagina_no_revela_si_el_correo_ya_tiene_cuenta(): void
    {
        // Un dueño de hogar podría invitar a cualquier dirección y abrir el
        // enlace para saber si esa persona usa Finlia: la página debe ser la
        // misma exista o no la cuenta (docs/SECURITY.md, enumeración).
        [, $sinCuenta] = $this->invite('nadie@finlia.test');
        [, $conCuenta] = $this->invite('alguien@finlia.test');
        User::factory()->create(['email' => 'alguien@finlia.test']);

        $normalizar = fn (string $html, string $email, string $token) => preg_replace(
            ['/Expira el [^<]+/', '/(csrf-token" content=|name="_token" value=)"[^"]*"/'],
            '',
            str_replace([$email, $token], '', $html),
        );

        $a = $this->get(route('invitations.show', $sinCuenta))->getContent();
        $this->flushSession();
        $b = $this->get(route('invitations.show', $conCuenta))->getContent();

        $this->assertSame(
            $normalizar($a, 'nadie@finlia.test', $sinCuenta),
            $normalizar($b, 'alguien@finlia.test', $conCuenta),
        );
    }

    public function test_una_invitacion_caducada_no_se_recuerda(): void
    {
        [$household, $token] = $this->invite();
        $household->invitations()->update(['expires_at' => now()->subDay()]);

        $this->get(route('invitations.show', $token))
            ->assertOk()
            ->assertSee('ha expirado')
            ->assertDontSee('Crear mi cuenta')
            ->assertSessionMissing('invitation_token');
    }

    public function test_una_invitacion_ya_aceptada_no_se_puede_reusar_sin_sesion(): void
    {
        [$household, $token] = $this->invite();
        $household->invitations()->update(['status' => 'accepted']);

        $this->get(route('invitations.show', $token))
            ->assertOk()
            ->assertSee('ya no está disponible')
            ->assertSessionMissing('invitation_token');
    }

    // ---- Iniciar sesión desde la invitación ----

    public function test_quien_ya_tiene_cuenta_vuelve_a_la_invitacion_tras_entrar(): void
    {
        [, $token] = $this->invite();
        User::factory()->create(['email' => self::INVITED]);

        $this->get(route('invitations.show', $token));

        $this->post(route('login'), ['email' => self::INVITED, 'password' => 'password'])
            ->assertRedirect(route('invitations.show', $token));
    }

    // ---- Registrarse desde la invitación ----

    public function test_el_registro_desde_la_invitacion_muestra_el_correo_fijo(): void
    {
        [, $token] = $this->invite();
        $this->get(route('invitations.show', $token));

        $this->get(route('register'))
            ->assertOk()
            ->assertSee('Te unes a «Hogar Durán»')
            ->assertSee('value="'.self::INVITED.'"', false)
            ->assertSee('readonly', false);
    }

    public function test_registrarse_desde_la_invitacion_no_crea_hogar_propio_y_pide_confirmar(): void
    {
        [$household, $token] = $this->invite();
        $this->get(route('invitations.show', $token));

        $this->register();

        $user = User::firstWhere('email', self::INVITED);

        $this->assertNotNull($user);
        $this->assertNull($user->email_verified_at, 'El enlace es transferible: el correo se sigue verificando.');
        $this->assertSame(0, $user->households()->count(), 'Nada de «Mi hogar» huérfano.');
        $this->assertFalse($household->fresh()->hasMember($user), 'Sin verificar no queda vinculado.');

        Mail::assertSent(VerifyEmailMail::class, fn ($mail) => $mail->hasTo(self::INVITED));
    }

    public function test_el_correo_del_formulario_no_se_puede_cambiar(): void
    {
        [, $token] = $this->invite();
        $this->get(route('invitations.show', $token));

        $this->register(['email' => 'intruso@finlia.test']);

        $this->assertDatabaseMissing('users', ['email' => 'intruso@finlia.test']);
        $this->assertDatabaseHas('users', ['email' => self::INVITED]);
    }

    public function test_al_confirmar_el_correo_entra_al_hogar_invitado(): void
    {
        [$household, $token] = $this->invite();
        $this->get(route('invitations.show', $token));
        $this->register();

        $user = User::firstWhere('email', self::INVITED);

        $this->get($this->verificationUrl($user))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('household_id', $household->id);

        $user->refresh();
        $this->assertTrue($household->fresh()->hasMember($user));
        $this->assertSame(1, $user->households()->count(), 'Un solo hogar: el invitado.');
        $this->assertDatabaseHas('household_invitations', [
            'household_id' => $household->id,
            'status' => 'accepted',
            'accepted_by_user_id' => $user->id,
        ]);
    }

    public function test_confirmar_desde_otro_dispositivo_tambien_la_vincula(): void
    {
        [$household, $token] = $this->invite();
        $this->get(route('invitations.show', $token));
        $this->register();

        $user = User::firstWhere('email', self::INVITED);

        // El enlace se abre en otro navegador: ninguna sesión compartida.
        $this->post(route('logout'));
        $this->flushSession();

        $this->get($this->verificationUrl($user))
            ->assertRedirect(route('login'));

        $this->assertTrue($household->fresh()->hasMember($user->fresh()));
    }

    public function test_si_la_invitacion_caduca_antes_de_confirmar_recibe_su_hogar_propio(): void
    {
        [$household, $token] = $this->invite();
        $this->get(route('invitations.show', $token));
        $this->register();

        $user = User::firstWhere('email', self::INVITED);
        $household->invitations()->update(['expires_at' => now()->subMinute()]);

        $this->get($this->verificationUrl($user));

        $user->refresh();
        $this->assertFalse($household->fresh()->hasMember($user));
        $this->assertTrue($user->households()->where('name', 'Mi hogar')->exists(), 'Nadie se queda sin hogar.');
    }

    public function test_un_registro_normal_no_cambia(): void
    {
        $this->register(['email' => 'maria@finlia.test']);

        $user = User::firstWhere('email', 'maria@finlia.test');
        $this->assertTrue($user->households()->where('name', 'Mi hogar')->exists());

        // Confirmar no le añade nada: ya tenía hogar.
        $this->get($this->verificationUrl($user));
        $this->assertSame(1, $user->fresh()->households()->count());
    }

    // ---- Aceptar sigue exigiendo correo verificado ----

    public function test_una_cuenta_sin_verificar_no_puede_aceptar_la_invitacion(): void
    {
        [$household, $token] = $this->invite();
        $unverified = User::factory()->unverified()->create(['email' => self::INVITED]);

        $this->actingAs($unverified)
            ->post(route('invitations.accept', $token))
            ->assertRedirect(route('verification.notice'));

        $this->assertFalse($household->fresh()->hasMember($unverified));
    }
}
