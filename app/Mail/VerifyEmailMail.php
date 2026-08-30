<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Verificación del correo del registro (Plan 01, ADR-0029).
 *
 * La notificación nativa de Laravel es markdown en inglés; esta replica el
 * patrón de la invitación y el digest (ADR-0015): HTML autocontenido en
 * español + texto plano. Sin datos financieros: solo el nombre y el enlace
 * firmado (60 min, config auth.verification.expire).
 */
class VerifyEmailMail extends Mailable
{
    public function __construct(
        public readonly User $user,
        public readonly string $verificationUrl,
        public readonly CarbonInterface $expiresAt,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Confirma tu correo en :app', ['app' => config('app.name')]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.auth.verify-email',
            text: 'emails.auth.verify-email-text',
            with: [
                'userName' => $this->user->name,
                'verificationUrl' => $this->verificationUrl,
                'expiresAt' => $this->expiresAt,
                'appName' => config('app.name'),
            ],
        );
    }
}
