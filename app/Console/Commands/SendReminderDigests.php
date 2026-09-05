<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\ReminderStatus;
use App\Mail\ReminderDigest;
use App\Models\Household;
use App\Services\ReminderService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Throwable;

/**
 * Digest diario de recordatorios por correo (Épica 9, ADR-0028).
 *
 * Lo ejecuta el Scheduler a diario (cron de Hostinger → schedule:run,
 * sin workers persistentes). Reglas anti-spam:
 *
 *  - Solo hogares con recordatorios activos.
 *  - Solo miembros que pidieron el correo (opt-in en su preferencia).
 *  - Solo si hay urgentes (vencidas o próximas). Sin urgentes, silencio.
 *  - Máximo un correo por hogar y miembro al día: el pivote guarda la
 *    última fecha de envío y la corrida la respeta (idempotente).
 *
 * El envío es síncrono a propósito (Fase 1): con Brevo free (300 correos
 * al día) y una base pequeña sobra, y corre en el proceso del cron de
 * madrugada — jamás añade latencia al camino HTTP. Cuando los opt-in con
 * urgentes rocen ~200-250 al día, la Fase 2 es despachar un Job
 * SendReminderDigest por destinatario (que envía y marca el pivote DESPUÉS
 * de enviar de verdad) y procesarlo con `queue:work --stop-when-empty`
 * (cola database, ADR-0008). Encolar el Mailable directo no vale: marcaría
 * "enviado" al despachar y un worker caído dejaría el día sin correo.
 */
class SendReminderDigests extends Command
{
    protected $signature = 'finlia:send-reminder-digests';

    protected $description = 'Envía el digest diario de recordatorios urgentes a quienes lo pidieron (Épica 9, ADR-0028)';

    public function handle(ReminderService $reminders): int
    {
        // Misma regla que las invitaciones (ADR-0015): con transports de
        // desarrollo o el correo apagado, no hay digest que prometer.
        if (! mail_is_deliverable()) {
            $this->info('Correo desactivado o sin transporte real: no se envía el digest.');

            return self::SUCCESS;
        }

        $today = Carbon::now(config('app.timezone'))->startOfDay();

        // Hogares con recordatorios activos + miembros opt-in que aún no
        // recibieron su digest de hoy (idempotencia por pivote). Nunca a
        // correos sin verificar: cinturón y suspenderes del Plan 01 — una
        // dirección no confirmada puede ser de otra persona.
        $households = Household::query()
            ->where('reminders_enabled', true)
            ->with(['members' => function ($query) use ($today) {
                $query->wherePivot('reminders_email', true)
                    ->whereNotNull('users.email_verified_at')
                    ->whereNull('users.deletion_requested_at') // excluir cuentas suspendidas (Plan 05)
                    ->where(function ($query) use ($today) {
                        $query->whereNull('household_user.last_reminder_digest_at')
                            ->orWhereDate('household_user.last_reminder_digest_at', '<', $today->toDateString());
                    });
            }])
            ->get();

        if ($households->isEmpty()) {
            $this->info('Sin hogares con digest pendiente.');

            return self::SUCCESS;
        }

        $sent = 0;

        foreach ($households as $household) {
            if ($household->members->isEmpty()) {
                continue;
            }

            // Summary fresco, no cacheado: la corrida es diaria y en frío.
            $summary = $reminders->summary($household->id);

            // Sin urgentes no hay correo: el silencio también es información.
            if ($summary['attention'] === 0) {
                continue;
            }

            $urgent = $reminders->list($household->id)->filter(
                fn (array $item) => $item['status'] === ReminderStatus::Overdue
                    || $item['status'] === ReminderStatus::Upcoming,
            );

            foreach ($household->members as $member) {
                try {
                    Mail::to($member)->send(new ReminderDigest(
                        $household->name,
                        $summary,
                        $urgent,
                        URL::temporarySignedRoute(
                            'reminders.unsubscribe',
                            now()->addDays(60),
                            ['user' => $member->id, 'household' => $household->id],
                        ),
                    ));
                } catch (Throwable $e) {
                    // Un buzón que rebota no puede frenar el resto de la
                    // corrida. Sin pivot actualizado, mañana reintenta.
                    Log::warning('Digest de recordatorios falló', [
                        'household_id' => $household->id,
                        'user_id' => $member->id,
                        'error' => $e->getMessage(),
                    ]);

                    continue;
                }

                $household->members()->updateExistingPivot(
                    $member->id,
                    ['last_reminder_digest_at' => now()],
                );

                $sent++;
            }
        }

        $this->info("Digest enviado a {$sent} miembro(s).");

        return self::SUCCESS;
    }
}
