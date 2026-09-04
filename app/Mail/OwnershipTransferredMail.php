<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Household;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Notifica al nuevo propietario que heredó el hogar (Plan 05, ADR-0033).
 */
class OwnershipTransferredMail extends Mailable
{
    public readonly string $newOwnerName;
    public readonly string $householdName;
    public readonly string $appName;

    public function __construct(User $newOwner, Household $household)
    {
        $this->newOwnerName = $newOwner->name;
        $this->householdName = $household->name;
        $this->appName = config('app.name');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Ahora eres administrador/a de :household en :app', [
                'household' => $this->householdName,
                'app' => config('app.name'),
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.account.ownership-transferred',
            text: 'emails.account.ownership-transferred-text',
            with: [
                'newOwnerName' => $this->newOwnerName,
                'householdName' => $this->householdName,
                'appName' => $this->appName,
                'dashboardUrl' => route('dashboard'),
            ],
        );
    }
}
