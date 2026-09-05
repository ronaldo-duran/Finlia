<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Correo que entrega el ZIP de exportación al usuario (Plan 06, ADR-0034).
 * Se envía desde el cron finlia:process-export-requests, nunca en una
 * petición HTTP (el ZIP puede ser grande).
 */
class DataExportReadyMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly string $zipPath,
        public readonly string $zipName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Tus datos de Finlia están listos',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.data-export-ready',
        );
    }

    public function attachments(): array
    {
        return [
            Attachment::fromPath($this->zipPath)
                ->as($this->zipName)
                ->withMime('application/zip'),
        ];
    }
}
