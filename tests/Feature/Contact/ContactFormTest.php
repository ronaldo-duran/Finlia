<?php

namespace Tests\Feature\Contact;

use App\Enums\ContactReason;
use App\Mail\ContactMessageReceivedMail;
use App\Models\ContactMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Formulario público de contacto.
 *
 * En pruebas `mail.default` es `array`, que Finlia trata como "no hay bandeja
 * real": los tests que esperan correo lo cambian a `smtp` con Mail::fake().
 */
class ContactFormTest extends TestCase
{
    use RefreshDatabase;

    private function datosValidos(array $sobrescribir = []): array
    {
        return array_merge([
            'reason' => ContactReason::Suggestion->value,
            'name' => 'Camila Restrepo',
            'email' => 'camila@ejemplo.test',
            'body' => 'Sería útil poder exportar los movimientos del mes a una hoja de cálculo.',
        ], $sobrescribir);
    }

    public function test_el_formulario_es_publico(): void
    {
        $this->get(route('contact.create'))
            ->assertOk()
            ->assertSee('Motivo', false);
    }

    public function test_un_visitante_sin_cuenta_puede_escribir(): void
    {
        $this->post(route('contact.store'), $this->datosValidos())
            ->assertRedirect(route('contact.create'))
            ->assertSessionHas('status');

        $mensaje = ContactMessage::sole();

        $this->assertSame(ContactReason::Suggestion, $mensaje->reason);
        $this->assertSame('camila@ejemplo.test', $mensaje->email);
        $this->assertNull($mensaje->user_id);
    }

    /**
     * Sin sesión, la IP es el único rastro para frenar abuso, así que se
     * guarda. Con sesión no, porque ya está el user_id: guardarla además
     * sería recolectar de más.
     */
    public function test_la_ip_solo_se_guarda_cuando_el_envio_es_anonimo(): void
    {
        $this->post(route('contact.store'), $this->datosValidos());
        $this->assertNotNull(ContactMessage::sole()->ip_address);

        ContactMessage::query()->delete();

        $usuario = User::factory()->create();
        $this->actingAs($usuario)->post(route('contact.store'), $this->datosValidos());

        $mensaje = ContactMessage::sole();
        $this->assertNull($mensaje->ip_address);
        $this->assertSame($usuario->id, $mensaje->user_id);
    }

    public function test_el_campo_trampa_descarta_el_envio(): void
    {
        $this->post(route('contact.store'), $this->datosValidos([
            'sitio_web' => 'http://spam.example',
        ]))->assertSessionHasErrors('sitio_web');

        $this->assertSame(0, ContactMessage::count());
    }

    public function test_el_motivo_de_error_no_se_acepta_por_el_formulario_publico(): void
    {
        $this->post(route('contact.store'), $this->datosValidos([
            'reason' => ContactReason::Bug->value,
        ]))->assertSessionHasErrors('reason');

        $this->assertSame(0, ContactMessage::count());
    }

    public function test_valida_correo_y_longitud_del_mensaje(): void
    {
        $this->post(route('contact.store'), $this->datosValidos([
            'email' => 'no-es-un-correo',
            'body' => 'corto',
        ]))->assertSessionHasErrors(['email', 'body']);

        $this->assertSame(0, ContactMessage::count());
    }

    public function test_avisa_al_buzon_del_producto_y_permite_responder_a_quien_escribe(): void
    {
        Mail::fake();
        config([
            'mail.default' => 'smtp',
            'finlia.mail.enabled' => true,
            'finlia.contact.inbox' => 'hola@finlia.test',
        ]);

        $this->post(route('contact.store'), $this->datosValidos());

        Mail::assertSent(ContactMessageReceivedMail::class, function (ContactMessageReceivedMail $mail) {
            return $mail->hasTo('hola@finlia.test')
                && $mail->hasReplyTo('camila@ejemplo.test');
        });
    }

    /**
     * Sin buzón configurado el mensaje se guarda igual: el registro nunca
     * depende del correo.
     */
    public function test_sin_buzon_configurado_el_mensaje_se_guarda_sin_avisar(): void
    {
        Mail::fake();
        config(['mail.default' => 'smtp', 'finlia.contact.inbox' => null]);

        $this->post(route('contact.store'), $this->datosValidos())
            ->assertSessionHas('status');

        $this->assertSame(1, ContactMessage::count());
        Mail::assertNothingSent();
    }

    public function test_el_cuarto_envio_en_una_hora_se_frena(): void
    {
        foreach (range(1, 3) as $i) {
            $this->post(route('contact.store'), $this->datosValidos())
                ->assertRedirect(route('contact.create'));
        }

        $this->post(route('contact.store'), $this->datosValidos())
            ->assertTooManyRequests();

        $this->assertSame(3, ContactMessage::count());
    }
}
