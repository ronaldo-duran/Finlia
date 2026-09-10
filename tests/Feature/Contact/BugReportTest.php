<?php

namespace Tests\Feature\Contact;

use App\Enums\ContactReason;
use App\Models\ContactMessage;
use App\Models\TermsVersion;
use App\Models\User;
use App\Services\HouseholdService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Reporte de error desde dentro de la aplicación.
 *
 * Exige sesión a propósito: así el reporte llega con el autor y el contexto
 * técnico ya adjuntos, sin preguntarle nada más a quien reporta.
 */
class BugReportTest extends TestCase
{
    use RefreshDatabase;

    private function usuarioConHogar(): User
    {
        $usuario = User::factory()->create();
        app(HouseholdService::class)->createHousehold($usuario->id, 'Hogar de prueba');

        $terminos = TermsVersion::current();
        if ($terminos !== null) {
            $usuario->acceptTerms($terminos, '127.0.0.1');
        }

        return $usuario->fresh();
    }

    public function test_un_invitado_no_puede_reportar(): void
    {
        $this->get(route('bug-report.create'))->assertRedirect(route('login'));

        $this->post(route('bug-report.store'), ['body' => str_repeat('a', 30)])
            ->assertRedirect(route('login'));

        $this->assertSame(0, ContactMessage::count());
    }

    public function test_registra_el_reporte_con_el_autor_y_el_contexto(): void
    {
        $usuario = $this->usuarioConHogar();

        $this->actingAs($usuario)
            ->withHeader('User-Agent', 'Mozilla/5.0 (Linux; Android 14)')
            ->post(route('bug-report.store'), [
                'body' => 'Registré un gasto de mercado y el saldo de la cuenta no cambió.',
                'origen' => 'https://app.finlia.test/movimientos',
                'viewport' => '390x844',
            ])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('status');

        $mensaje = ContactMessage::sole();

        $this->assertSame(ContactReason::Bug, $mensaje->reason);
        $this->assertSame($usuario->id, $mensaje->user_id);
        // El nombre y el correo salen de la cuenta, no del formulario.
        $this->assertSame($usuario->email, $mensaje->email);
        $this->assertSame($usuario->name, $mensaje->name);

        $this->assertSame('390x844', $mensaje->context['Viewport']);
        $this->assertSame('https://app.finlia.test/movimientos', $mensaje->context['Pantalla']);
        $this->assertStringContainsString('Android', $mensaje->context['Navegador']);
        $this->assertSame(config('finlia.version'), $mensaje->context['Versión']);
    }

    /**
     * Con sesión ya está el user_id: la IP sería un dato personal de más.
     */
    public function test_no_guarda_la_ip_de_un_usuario_autenticado(): void
    {
        $usuario = $this->usuarioConHogar();

        $this->actingAs($usuario)->post(route('bug-report.store'), [
            'body' => 'La pantalla de reportes se queda en blanco al elegir «Año».',
        ]);

        $this->assertNull(ContactMessage::sole()->ip_address);
    }

    public function test_exige_una_descripcion_con_algo_de_detalle(): void
    {
        $usuario = $this->usuarioConHogar();

        $this->actingAs($usuario)
            ->post(route('bug-report.store'), ['body' => 'no sirve'])
            ->assertSessionHasErrors('body');

        $this->assertSame(0, ContactMessage::count());
    }

    public function test_el_campo_trampa_tambien_protege_el_reporte(): void
    {
        $usuario = $this->usuarioConHogar();

        $this->actingAs($usuario)
            ->post(route('bug-report.store'), [
                'body' => 'Mensaje suficientemente largo para pasar la validación.',
                'sitio_web' => 'http://spam.example',
            ])
            ->assertSessionHasErrors('sitio_web');

        $this->assertSame(0, ContactMessage::count());
    }

    /**
     * El contexto llega del navegador, así que es manipulable. Se recorta para
     * que nadie use el reporte como sitio donde volcar texto arbitrario.
     */
    public function test_el_contexto_manipulado_se_recorta(): void
    {
        $usuario = $this->usuarioConHogar();

        $this->actingAs($usuario)->post(route('bug-report.store'), [
            'body' => 'Mensaje suficientemente largo para pasar la validación.',
            'viewport' => str_repeat('9', 500),
        ])->assertSessionHasErrors('viewport');

        $this->assertSame(0, ContactMessage::count());
    }
}
