<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Aviso al correo ANTIGUO de que la cuenta cambió de correo (Plan 02,
 * ADR-0030).
 *
 * La pierna antifraude del flujo: si alguien robó la sesión y movió la
 * cuenta a otra bandeja, el dueño real se entera donde siempre recibió y
 * tiene la vía de reacción (recuperar la contraseña por correo).
 */
class EmailChangedNoticeMail extends Mailable
{
    public function __construct(
        public readonly string $userName,
        public readonly string $oldEmail,
        public readonly string $newEmail,
        public readonly string $recoveryUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Tu correo de :app cambió', ['app' => config('app.name')]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.profile.email-changed-notice',
            text: 'emails.profile.email-changed-notice-text',
            with: [
                'userName' => $this->userName,
                'oldEmail' => $this->oldEmail,
                'newEmail' => $this->newEmail,
                'recoveryUrl' => $this->recoveryUrl,
                'appName' => config('app.name'),
            ],
        );
    }
}
