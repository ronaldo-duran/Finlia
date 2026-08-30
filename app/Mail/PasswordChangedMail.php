<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Aviso de contraseña cambiada (Plan 02, ADR-0030).
 *
 * Transaccional de seguridad: el destinatario acaba de hacer la acción
 * dentro de la app, pero el aviso igual sale — si NO fue él, es su señal
 * de reaccionar (recuperar la contraseña revoca las sesiones). Sin datos
 * financieros, sin IP (decisión pendiente del plan 06).
 */
class PasswordChangedMail extends Mailable
{
    public function __construct(
        public readonly string $userName,
        public readonly string $recoveryUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Tu contraseña de :app cambió', ['app' => config('app.name')]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.profile.password-changed',
            text: 'emails.profile.password-changed-text',
            with: [
                'userName' => $this->userName,
                'recoveryUrl' => $this->recoveryUrl,
                'appName' => config('app.name'),
            ],
        );
    }
}
