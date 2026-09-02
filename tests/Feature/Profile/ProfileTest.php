<?php

namespace Tests\Feature\Profile;

use App\Mail\PasswordChangedMail;
use App\Models\User;
use Illuminate\Auth\Events\OtherDeviceLogout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Perfil: datos y contraseña (Plan 02, ADR-0030). El cambio de correo
 * tiene su propio archivo (ChangeEmailTest).
 */
class ProfileTest extends TestCase
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

    // ---- Pantalla ----

    public function test_el_perfil_requiere_sesion(): void
    {
        $this->get(route('profile.edit'))->assertRedirect(route('login'));
    }

    public function test_el_perfil_muestra_al_propio_usuario(): void
    {
        $user = User::factory()->create(['name' => 'Ana Torres', 'email' => 'ana@ejemplo.com']);
        User::factory()->create(['email' => 'otro@ejemplo.com']);

        // Aislamiento (Plan 02): /perfil no conoce IDs ajenos — solo existe
        // el usuario autenticado; nada de otro puede aparecer aquí.
        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('ana@ejemplo.com')
            ->assertSee('Ana Torres')
            ->assertSee('Confirmado') // correo verificado por defecto del factory
            ->assertDontSee('otro@ejemplo.com');
    }

    // ---- Datos ----

    public function test_actualiza_el_nombre(): void
    {
        $user = User::factory()->create(['name' => 'Nombre Viejo']);

        // El formulario trae también la fecha (obligatoria desde el plan 04);
        // el resto de datos personales tiene su propio archivo (PersonalDataTest).
        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->put(route('profile.update'), [
                'name' => 'Nombre Nuevo',
                'birth_date' => $user->birth_date->toDateString(),
            ])
            ->assertRedirect(route('profile.edit'));

        $this->assertSame('Nombre Nuevo', $user->fresh()->name);
    }

    public function test_el_nombre_es_obligatorio(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('profile.update'), ['name' => ''])
            ->assertSessionHasErrors('name');

        $this->assertSame($user->name, $user->fresh()->name);
    }

    // ---- Contraseña ----

    public function test_la_contrasena_actual_es_obligatoria_y_valida(): void
    {
        $user = User::factory()->create(['password' => 'clave-correcta-1']);

        // Sin la actual.
        $this->actingAs($user)
            ->put(route('profile.password.update'), [
                'password' => 'nueva-clave-123',
                'password_confirmation' => 'nueva-clave-123',
            ])
            ->assertSessionHasErrors('current_password');

        // Actual equivocada.
        $this->actingAs($user)
            ->put(route('profile.password.update'), [
                'current_password' => 'clave-equivocada',
                'password' => 'nueva-clave-123',
                'password_confirmation' => 'nueva-clave-123',
            ])
            ->assertSessionHasErrors('current_password');

        // Nada cambió.
        $this->assertTrue(Hash::check('clave-correcta-1', $user->fresh()->password));
    }

    public function test_cambia_la_contrasena_con_la_actual_correcta(): void
    {
        Mail::fake();
        $user = User::factory()->create([
            'name' => 'Ana Torres',
            'email' => 'ana@ejemplo.com',
            'password' => 'clave-correcta-1',
        ]);

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->put(route('profile.password.update'), [
                'current_password' => 'clave-correcta-1',
                'password' => 'nueva-clave-123',
                'password_confirmation' => 'nueva-clave-123',
            ])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHas('status');

        $this->assertTrue(Hash::check('nueva-clave-123', $user->fresh()->password));
        $this->assertFalse(Hash::check('clave-correcta-1', $user->fresh()->password));

        // Aviso antifraude al correo vigente.
        Mail::assertSent(PasswordChangedMail::class, fn ($mail) => $mail->hasTo('ana@ejemplo.com'));
    }

    public function test_la_sesion_actual_sigue_viva_tras_cambiar_la_contrasena(): void
    {
        Mail::fake();
        $user = User::factory()->create(['password' => 'clave-correcta-1']);

        $this->actingAs($user)
            ->put(route('profile.password.update'), [
                'current_password' => 'clave-correcta-1',
                'password' => 'nueva-clave-123',
                'password_confirmation' => 'nueva-clave-123',
            ])
            ->assertRedirect();

        // El dispositivo que hizo el cambio no vuelve a iniciar sesión.
        $this->get(route('profile.edit'))->assertOk();
    }

    public function test_cambiar_la_contrasena_revoca_las_demas_sesiones(): void
    {
        Mail::fake();
        Event::fake([OtherDeviceLogout::class]);

        $user = User::factory()->create(['password' => 'clave-correcta-1']);
        $hashAnterior = $user->password;

        $this->actingAs($user)
            ->put(route('profile.password.update'), [
                'current_password' => 'clave-correcta-1',
                'password' => 'nueva-clave-123',
                'password_confirmation' => 'nueva-clave-123',
            ])
            ->assertRedirect();

        // logoutOtherDevices disparó la revocación de dispositivos.
        Event::assertDispatched(OtherDeviceLogout::class);

        // La sesión de OTRO dispositivo quedó ligada al hash anterior
        // (así la deja AuthenticateSession al pasar): al volver a tocar la
        // app, el middleware la cierra porque el hash ya no coincide.
        $this->withSession(['password_hash_web' => $hashAnterior]);

        $this->get(route('profile.edit'))->assertRedirect(route('login'));
    }

    // ---- Contenido de los correos ----

    public function test_correo_de_contrasena_renderiza_en_espanol(): void
    {
        $mail = new PasswordChangedMail('María González', 'https://finlia.test/recuperar-contrasena');

        $mail->assertSeeInHtml('Tu contraseña cambió');
        $mail->assertSeeInHtml('María González');
        $mail->assertSeeInHtml('¿No fuiste tú?');
        $mail->assertSeeInText('Recupera el acceso');
    }
}
