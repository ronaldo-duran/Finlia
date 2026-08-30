<?php

declare(strict_types=1);

namespace App\Mail;

use Carbon\CarbonInterface;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Confirmación del cambio de correo (Plan 02, ADR-0030).
 *
 * Va a la bandeja NUEVA: solo quien la controla puede completar el cambio.
 * El enlace lleva un token aleatorio (hash sha256 en la base, patrón de
 * household_invitations) con expiración de 60 minutos.
 */
class ConfirmEmailChangeMail extends Mailable
{
    public function __construct(
        public readonly string $userName,
        public readonly string $newEmail,
        public readonly string $confirmationUrl,
        public readonly CarbonInterface $expiresAt,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Confirma tu nuevo correo en :app', ['app' => config('app.name')]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.profile.confirm-email-change',
            text: 'emails.profile.confirm-email-change-text',
            with: [
                'userName' => $this->userName,
                'newEmail' => $this->newEmail,
                'confirmationUrl' => $this->confirmationUrl,
                'expiresAt' => $this->expiresAt,
                'appName' => config('app.name'),
            ],
        );
    }
}
