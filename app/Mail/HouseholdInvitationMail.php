<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\HouseholdInvitation;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Invitación para unirse a un hogar (ADR-0015).
 *
 * Es uno de los dos únicos correos que envía Finlia (el otro es la
 * recuperación de contraseña, nativa de Laravel). No lleva ningún dato
 * financiero del hogar: solo el nombre del hogar, quién invita y el enlace.
 *
 * El token plano viaja SOLO aquí y en el enlace que ve el administrador;
 * en base de datos se guarda su hash (ADR-0003).
 */
class HouseholdInvitationMail extends Mailable
{
    public function __construct(
        public readonly HouseholdInvitation $invitation,
        private readonly string $plainToken,
        public readonly ?string $invitedByName = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Te invitaron a :household en Finlia', [
                'household' => $this->invitation->household->name,
            ]),
        );
    }

    public function content(): Content
    {
        $data = [
            'householdName' => $this->invitation->household->name,
            'invitedByName' => $this->invitedByName,
            'acceptUrl' => route('invitations.show', $this->plainToken),
            'expiresAt' => $this->invitation->expires_at,
            'appName' => config('app.name'),
        ];

        return new Content(
            view: 'emails.households.invitation',
            text: 'emails.households.invitation-text',
            with: $data,
        );
    }
}
