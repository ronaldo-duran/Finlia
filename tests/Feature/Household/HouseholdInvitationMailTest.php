<?php

namespace Tests\Feature\Household;

use App\Enums\HouseholdRole;
use App\Mail\HouseholdInvitationMail;
use App\Models\Household;
use App\Models\User;
use App\Services\HouseholdService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Envío del correo de invitación (ADR-0015).
 *
 * En el entorno de pruebas `mail.default` es `array` (transport que no
 * entrega), así que cada test que espere un envío real lo cambia a `smtp`
 * de forma explícita.
 */
class HouseholdInvitationMailTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: Household}
     */
    private function setupHousehold(): array
    {
        $owner = User::factory()->create(['name' => 'Ronaldo']);
        $household = app(HouseholdService::class)->createHousehold($owner->id, 'Hogar Correo');

        return [$owner, $household];
    }

    private function withDeliverableMailer(): void
    {
        config(['mail.default' => 'smtp', 'finlia.mail.enabled' => true]);
    }

    public function test_invitar_envia_el_correo_al_invitado(): void
    {
        Mail::fake();
        $this->withDeliverableMailer();

        [$owner, $household] = $this->setupHousehold();

        $this->actingAs($owner)->post(route('households.invitations.store', $household), [
            'email' => 'Amigo@Finlia.test',
            'role' => 'member',
        ])->assertRedirect(route('households.show', $household));

        Mail::assertSent(HouseholdInvitationMail::class, function (HouseholdInvitationMail $mail): bool {
            // El correo se normaliza a minúsculas antes de guardar y enviar.
            return $mail->hasTo('amigo@finlia.test')
                && $mail->invitedByName === 'Ronaldo'
                && $mail->invitation->household->name === 'Hogar Correo';
        });
    }

    public function test_el_correo_lleva_el_enlace_con_el_token_plano(): void
    {
        Mail::fake();
        $this->withDeliverableMailer();

        [, $household] = $this->setupHousehold();

        [, $plainToken, $emailSent] = app(HouseholdService::class)
            ->inviteMember($household, 'invitado@finlia.test', HouseholdRole::Member, 'Vanessa');

        $this->assertTrue($emailSent);

        Mail::assertSent(HouseholdInvitationMail::class, function (HouseholdInvitationMail $mail) use ($plainToken): bool {
            $rendered = $mail->render();

            return str_contains($rendered, route('invitations.show', $plainToken))
                && str_contains($rendered, 'Hogar Correo')
                && str_contains($rendered, 'Vanessa');
        });
    }

    public function test_el_asunto_menciona_el_hogar(): void
    {
        Mail::fake();
        $this->withDeliverableMailer();

        [, $household] = $this->setupHousehold();

        app(HouseholdService::class)
            ->inviteMember($household, 'invitado@finlia.test', HouseholdRole::Member);

        Mail::assertSent(HouseholdInvitationMail::class, function (HouseholdInvitationMail $mail): bool {
            return $mail->envelope()->subject === 'Te invitaron a Hogar Correo en Finlia';
        });
    }

    public function test_no_se_envia_si_el_correo_esta_desactivado(): void
    {
        Mail::fake();
        config(['mail.default' => 'smtp', 'finlia.mail.enabled' => false]);

        [, $household] = $this->setupHousehold();

        [$invitation, , $emailSent] = app(HouseholdService::class)
            ->inviteMember($household, 'invitado@finlia.test', HouseholdRole::Member);

        Mail::assertNothingSent();
        $this->assertFalse($emailSent);
        // La invitación se crea igual: el enlace manual sigue siendo válido.
        $this->assertSame('pending', $invitation->status->value);
    }

    public function test_no_se_envia_con_un_transport_que_no_entrega(): void
    {
        Mail::fake();
        // `array` y `log` son los transports de desarrollo: no hay bandeja real.
        config(['mail.default' => 'array']);

        [, $household] = $this->setupHousehold();

        [, , $emailSent] = app(HouseholdService::class)
            ->inviteMember($household, 'invitado@finlia.test', HouseholdRole::Member);

        Mail::assertNothingSent();
        $this->assertFalse($emailSent);
    }

    public function test_un_fallo_del_servidor_de_correo_no_rompe_la_invitacion(): void
    {
        $this->withDeliverableMailer();

        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('SMTP caído'));

        [$owner, $household] = $this->setupHousehold();

        $this->actingAs($owner)->post(route('households.invitations.store', $household), [
            'email' => 'amigo@finlia.test',
            'role' => 'member',
        ])
            ->assertRedirect(route('households.show', $household))
            ->assertSessionHas('invitation_link')
            ->assertSessionHas('invitation_email_sent', false);

        $this->assertDatabaseHas('household_invitations', [
            'email' => 'amigo@finlia.test',
            'status' => 'pending',
        ]);
    }

    public function test_la_vista_avisa_al_owner_cuando_el_correo_si_salio(): void
    {
        Mail::fake();
        $this->withDeliverableMailer();

        [$owner, $household] = $this->setupHousehold();

        $this->actingAs($owner)
            ->post(route('households.invitations.store', $household), [
                'email' => 'amigo@finlia.test',
                'role' => 'member',
            ])
            ->assertSessionHas('invitation_email_sent', true);

        $this->actingAs($owner)
            ->get(route('households.show', $household))
            ->assertSee('Invitación enviada a', false);
    }
}
