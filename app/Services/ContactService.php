<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ContactReason;
use App\Mail\ContactMessageReceivedMail;
use App\Models\ContactMessage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Registro de mensajes de contacto y aviso al responsable del producto.
 *
 * Sin dependencias de la capa HTTP (ADR-0010): recibe datos explícitos, no la
 * Request. Quien llama decide de dónde salen la IP, el usuario y el contexto.
 */
class ContactService
{
    /**
     * @param  array<string, mixed>|null  $context  Contexto técnico de un reporte de error
     */
    public function record(
        ContactReason $reason,
        string $name,
        string $email,
        string $body,
        ?int $userId = null,
        ?string $ipAddress = null,
        ?array $context = null,
    ): ContactMessage {
        $mensaje = new ContactMessage;
        $mensaje->fill([
            'reason' => $reason->value,
            'name' => $name,
            'email' => $email,
            'body' => $body,
        ]);

        // Fuera de fillable a propósito: los pone el servidor.
        $mensaje->user_id = $userId;
        // La IP solo tiene sentido guardarla cuando no hay usuario: es el
        // único rastro para frenar abuso. Con sesión, sobra.
        $mensaje->ip_address = $userId === null ? $ipAddress : null;
        $mensaje->context = $context;

        $mensaje->save();

        $this->notify($mensaje);

        return $mensaje;
    }

    /**
     * Avisa al buzón del producto. Un fallo aquí NUNCA tumba el envío: el
     * mensaje ya está guardado, y perderlo por un SMTP caído sería peor que
     * enterarse tarde.
     */
    private function notify(ContactMessage $mensaje): void
    {
        $buzon = config('finlia.contact.inbox');

        if ($buzon === null || ! mail_is_deliverable()) {
            return;
        }

        try {
            Mail::to($buzon)->send(new ContactMessageReceivedMail($mensaje));
        } catch (\Throwable $e) {
            // Sin el cuerpo del mensaje ni el correo de quien escribe: un log
            // no es sitio para datos personales.
            Log::warning('No se pudo avisar de un mensaje de contacto', [
                'contact_message_id' => $mensaje->id,
                'reason' => $mensaje->reason->value,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
