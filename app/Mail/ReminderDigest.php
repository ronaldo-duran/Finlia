<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

/**
 * Digest diario de recordatorios (Épica 9, ADR-0028).
 *
 * Un único correo al día por usuario, SOLO si tiene obligaciones urgentes
 * (vencidas o próximas), y únicamente si lo pidió (opt-in en su
 * preferencia del hogar). La app sigue siendo la verdad: el correo es un
 * aviso con dedo que trae de vuelta, jamás sustituye a /recordatorios ni
 * "apaga" nada al leerlo (ADR-0027).
 *
 * No implementa ShouldQueue todavía: en hosting compartido se envía
 * síncrono dentro del comando de madrugada. Activarlo después es añadir
 * `implements ShouldQueue` — el seam ya está (cola database, ADR-0008).
 */
class ReminderDigest extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * @param  array{overdue: int, upcoming: int, attention: int, total: int}  $summary
     * @param  Collection<int, array<string, mixed>>  $urgent  ítems con status overdue/upcoming
     */
    public function __construct(
        public readonly string $householdName,
        public readonly array $summary,
        public readonly Collection $urgent,
    ) {}

    public function envelope(): Envelope
    {
        $n = $this->summary['attention'];
        $count = $n === 1 ? '1 recordatorio pendiente' : $n.' recordatorios pendientes';

        return new Envelope(
            subject: __('Tienes :count en :household — :app', [
                'count' => $count,
                'household' => $this->householdName,
                'app' => config('app.name'),
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.reminders.digest',
            text: 'emails.reminders.digest-text',
            with: [
                'householdName' => $this->householdName,
                'summary' => $this->summary,
                'urgent' => $this->urgent,
                'url' => route('reminders.index'),
                'appName' => config('app.name'),
            ],
        );
    }
}
