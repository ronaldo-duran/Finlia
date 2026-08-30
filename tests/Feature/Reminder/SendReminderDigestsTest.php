<?php

namespace Tests\Feature\Reminder;

use App\Mail\ReminderDigest;
use App\Models\Household;
use App\Models\User;
use App\Services\HouseholdService;
use App\Services\ReminderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Digest diario de recordatorios por correo (Épica 9, ADR-0028): solo a
 * quienes lo pidieron, solo si hay urgentes, máximo uno por hogar y
 * miembro al día (idempotencia por pivote).
 */
class SendReminderDigestsTest extends TestCase
{
    use RefreshDatabase;

    private Household $household;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        // phpunit usa MAIL_MAILER=array (transport "falso", ADR-0015): el
        // comando se salta el envío con él. Los tests de envío declaran un
        // transporte real y Mail::fake() hace de SMTP.
        config(['mail.default' => 'smtp']);

        $this->owner = User::factory()->create();
        $this->household = app(HouseholdService::class)->createHousehold($this->owner->id, 'Hogar A');

        // Aviso vencido: garantiza "urgentes" para el digest.
        $this->household->reminders()->create([
            'title' => 'Tecnomecánica',
            'amount' => 250000,
            'due_date' => now()->subDays(2)->toDateString(),
        ]);
    }

    private function optIn(User $user): void
    {
        $this->household->members()->updateExistingPivot($user->id, [
            'reminders_email' => true,
        ]);
    }

    public function test_envia_solo_a_miembros_que_lo_pidieron(): void
    {
        Mail::fake();

        $silencioso = User::factory()->create();
        $this->household->members()->attach($silencioso->id, [
            'role' => 'member',
            'joined_at' => now(),
        ]);
        $this->optIn($this->owner);

        $this->artisan('finlia:send-reminder-digests')->assertSuccessful();

        Mail::assertSent(ReminderDigest::class, 1);
        Mail::assertSent(ReminderDigest::class, fn ($mail) => $mail->hasTo($this->owner->email));
        Mail::assertNotSent(ReminderDigest::class, fn ($mail) => $mail->hasTo($silencioso->email));
    }

    public function test_no_envia_a_miembros_sin_correo_verificado(): void
    {
        // Plan 01: nunca correos periódicos a direcciones sin confirmar —
        // pueden ser de otra persona (anti-squatting).
        Mail::fake();

        $sinVerificar = User::factory()->unverified()->create();
        $this->household->members()->attach($sinVerificar->id, [
            'role' => 'member',
            'joined_at' => now(),
        ]);
        $this->optIn($this->owner);
        $this->optIn($sinVerificar);

        $this->artisan('finlia:send-reminder-digests')->assertSuccessful();

        Mail::assertSent(ReminderDigest::class, 1);
        Mail::assertSent(ReminderDigest::class, fn ($mail) => $mail->hasTo($this->owner->email));
        Mail::assertNotSent(ReminderDigest::class, fn ($mail) => $mail->hasTo($sinVerificar->email));
    }

    public function test_marca_la_fecha_de_envio_en_el_pivote(): void
    {
        Mail::fake();
        $this->optIn($this->owner);

        $this->artisan('finlia:send-reminder-digests')->assertSuccessful();

        $this->assertDatabaseHas('household_user', [
            'household_id' => $this->household->id,
            'user_id' => $this->owner->id,
            'reminders_email' => true,
        ]);

        $this->assertNotNull(
            $this->household->members()->where('user_id', $this->owner->id)->value('last_reminder_digest_at'),
        );
    }

    public function test_sin_urgentes_no_envia_nada(): void
    {
        Mail::fake();
        $this->optIn($this->owner);

        // Todo queda "más adelante": ni vencidas ni próximas.
        $this->household->reminders()->create([
            'title' => 'Impuesto predial',
            'due_date' => now()->addMonths(3)->toDateString(),
        ]);
        $this->household->reminders()->where('title', 'Tecnomecánica')->delete();

        $this->artisan('finlia:send-reminder-digests')->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_hogar_con_recordatorios_desactivados_no_envia(): void
    {
        Mail::fake();
        $this->optIn($this->owner);
        $this->household->update(['reminders_enabled' => false]);

        $this->artisan('finlia:send-reminder-digests')->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_la_segunda_corrida_del_dia_no_duplica(): void
    {
        Mail::fake();
        $this->optIn($this->owner);

        $this->artisan('finlia:send-reminder-digests')->assertSuccessful();
        $this->artisan('finlia:send-reminder-digests')->assertSuccessful();

        Mail::assertSent(ReminderDigest::class, 1);
    }

    public function test_quien_ya_recibio_hoy_no_recibe_de_nuevo(): void
    {
        Mail::fake();
        $this->household->members()->updateExistingPivot($this->owner->id, [
            'reminders_email' => true,
            'last_reminder_digest_at' => now(),
        ]);

        $this->artisan('finlia:send-reminder-digests')->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_con_transporte_de_desarrollo_no_envia(): void
    {
        // Desarrollo y tests usan log/array: no hay bandeja real detrás,
        // así que el digest no corre (misma regla que las invitaciones).
        Mail::fake();
        config(['mail.default' => 'array']);
        $this->optIn($this->owner);

        $this->artisan('finlia:send-reminder-digests')->assertSuccessful();

        Mail::assertNothingSent();

        // Y el pivote no se marca: no se "gasta" el envío del día.
        $this->assertNull(
            $this->household->members()->where('user_id', $this->owner->id)->value('last_reminder_digest_at'),
        );
    }

    public function test_el_correo_se_renderiza_con_los_urgentes(): void
    {
        // Renderiza HTML y texto de verdad: caza errores de Blade que un
        // assertSent no toca (interpolaciones rotas, variables faltantes).
        $reminders = app(ReminderService::class);
        $baja = URL::temporarySignedRoute('reminders.unsubscribe', now()->addDays(60), [
            'user' => $this->owner->id,
            'household' => $this->household->id,
        ]);

        $html = (new ReminderDigest(
            $this->household->name,
            $reminders->summary($this->household->id),
            $reminders->list($this->household->id),
            $baja,
        ))->render();

        $this->assertStringContainsString('Tecnomecánica', $html);
        $this->assertStringContainsString('Vencida hace 2 días', $html);
        $this->assertStringContainsString(route('reminders.index'), $html);
        // La baja queda a un click, firmada por usuario+hogar. Blade escapa
        // los & de la query a &amp;: se compara la versión escapada.
        $this->assertStringContainsString(e($baja), $html);
    }
}
