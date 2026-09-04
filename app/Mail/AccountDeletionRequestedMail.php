<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Correo antifraude enviado al solicitar la eliminación de cuenta (Plan 05, ADR-0033).
 * Informa la fecha límite de purga y ofrece el enlace de reactivación.
 */
class AccountDeletionRequestedMail extends Mailable
{
    public readonly string $userName;
    public readonly string $deadline;
    public readonly string $reactivateUrl;
    public readonly string $appName;

    public function __construct(User $user)
    {
        $this->userName = $user->name;
        $this->deadline = $user->deletionDeadline()?->format('d/m/Y') ?? '';
        $this->reactivateUrl = route('account.reactivate');
        $this->appName = config('app.name');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Solicitaste eliminar tu cuenta de :app', ['app' => config('app.name')]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.account.deletion-requested',
            text: 'emails.account.deletion-requested-text',
            with: [
                'userName' => $this->userName,
                'deadline' => $this->deadline,
                'reactivateUrl' => $this->reactivateUrl,
                'appName' => $this->appName,
            ],
        );
    }
}
