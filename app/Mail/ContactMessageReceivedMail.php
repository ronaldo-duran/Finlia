<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\ContactMessage;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Aviso interno: alguien escribió por el formulario de contacto o reportó un
 * error desde la aplicación.
 *
 * Va al buzón del producto, no al usuario. El `replyTo` apunta a quien
 * escribió, para poder responderle sin copiar la dirección a mano.
 */
class ContactMessageReceivedMail extends Mailable
{
    public readonly string $reasonLabel;

    public readonly string $senderName;

    public readonly string $senderEmail;

    public readonly string $body;

    public readonly string $sentAt;

    public readonly bool $fromGuest;

    /** @var array<string, mixed> */
    public readonly array $context;

    public function __construct(ContactMessage $message)
    {
        $this->reasonLabel = $message->reason->label();
        $this->senderName = $message->name;
        $this->senderEmail = $message->email;
        $this->body = $message->body;
        $this->sentAt = $message->created_at->format('d/m/Y H:i');
        $this->fromGuest = $message->isFromGuest();
        $this->context = $message->context ?? [];
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '['.$this->reasonLabel.'] '.$this->senderName,
            replyTo: [new Address($this->senderEmail, $this->senderName)],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.contact.received',
            text: 'emails.contact.received-text',
        );
    }
}
