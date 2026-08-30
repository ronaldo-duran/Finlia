<?php

declare(strict_types=1);

namespace App\Services;

use App\Mail\ConfirmEmailChangeMail;
use App\Mail\EmailChangedNoticeMail;
use App\Mail\PasswordChangedMail;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Support\Stringable;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Gestión de la cuenta propia: contraseña y correo (Plan 02, ADR-0030).
 *
 * Invariante central: NINGÚN correo entra a users.email sin haber pasado
 * por una bandeja verificada — el cambio vive en columnas pending_* hasta
 * que el enlace del correo nuevo se confirma. Así el correo verificado
 * sigue siendo la llave de la cuenta (digest, recuperación, avisos).
 *
 * El token público nunca se guarda en claro (sha256), patrón de
 * household_invitations: un volcado de la base no revela enlaces válidos.
 */
class ProfileService
{
    private const TOKEN_LENGTH = 64;

    /** Vigencia del enlace de confirmación (la pantalla de perfil la muestra). */
    public const EMAIL_CHANGE_TTL_MINUTES = 60;

    /**
     * Cambia la contraseña. La invalidación de otras sesiones NO vive aquí:
     * es maquinaria de la sesión (Auth::logoutOtherDevices) y la ejecuta el
     * controlador, que sí tiene contexto HTTP (seam de services, ADR-0010).
     */
    public function changePassword(User $user, string $newPassword): void
    {
        $user->update(['password' => $newPassword]);

        // Aviso antifraude al correo vigente: si no fue el dueño, la
        // recuperación de contraseña es su vía de reacción.
        $this->send(
            $user->email,
            new PasswordChangedMail($user->name, route('password.request')),
        );
    }

    /**
     * Arranca el cambio de correo: guarda el pendiente (token hasheado,
     * 60 min) y envía la confirmación a la BANDEJA NUEVA. Solo quien
     * controla esa bandeja puede completar el cambio.
     */
    public function requestEmailChange(User $user, string $newEmail): void
    {
        $newEmail = str($newEmail)->lower()->trim()->toString();

        $plainToken = Str::random(self::TOKEN_LENGTH);

        $user->forceFill([
            'pending_email' => $newEmail,
            'pending_email_token' => $this->hashToken($plainToken),
            'pending_email_requested_at' => now(),
        ])->save();

        $this->send($newEmail, new ConfirmEmailChangeMail(
            $user->name,
            $newEmail,
            route('profile.email.confirm', ['token' => $plainToken]),
            now()->addMinutes(self::EMAIL_CHANGE_TTL_MINUTES),
        ));
    }

    /**
     * Completa el cambio desde el enlace del correo nuevo: swap atómico,
     * verificado por construcción y aviso al correo ANTIGUO.
     *
     * @throws ValidationException
     */
    public function confirmEmailChange(string $plainToken): User
    {
        /** @var Stringable $normalized */
        $normalized = str($plainToken)->trim();

        if ($normalized->length() !== self::TOKEN_LENGTH) {
            $this->rejectToken();
        }

        $user = User::query()
            ->where('pending_email_token', $this->hashToken($normalized->toString()))
            ->first();

        if ($user === null) {
            $this->rejectToken();
        }

        if ($user->pending_email_requested_at?->lt(now()->subMinutes(self::EMAIL_CHANGE_TTL_MINUTES))) {
            $this->clearPendingEmail($user);

            throw ValidationException::withMessages([
                'token' => __('Este enlace expiró. Pide el cambio de correo de nuevo desde tu perfil.'),
            ]);
        }

        $newEmail = (string) $user->pending_email;
        $oldEmail = $user->email;

        // Conflicto duro: otra cuenta VERIFICADA ganó ese correo mientras el
        // enlace viajaba. Una cuenta verificada probó su bandeja (Plan 01):
        // fuera del reclaim, sin excepciones.
        $takenByVerified = User::query()
            ->where('email', $newEmail)
            ->whereNotNull('email_verified_at')
            ->whereKeyNot($user->getKey())
            ->exists();

        if ($takenByVerified) {
            $this->clearPendingEmail($user);

            throw ValidationException::withMessages([
                'token' => __('Ese correo ya pertenece a otra cuenta confirmada. Pide el cambio de nuevo desde tu perfil.'),
            ]);
        }

        DB::transaction(function () use ($user, $newEmail): void {
            // Fantasma sin verificar con ese correo: mismo reclaim del
            // registro (Plan 01) — es inerte por construcción (sin verificar
            // no puede haber creado ningún dato).
            User::query()
                ->where('email', $newEmail)
                ->whereNull('email_verified_at')
                ->delete();

            $user->forceFill([
                'email' => $newEmail,
                // Confirmar el enlace ES la verificación: quien lo pulsó
                // controla la bandeja nueva.
                'email_verified_at' => now(),
                'pending_email' => null,
                'pending_email_token' => null,
                'pending_email_requested_at' => null,
            ])->save();
        });

        // Aviso al correo ANTIGUO: la pierna antifraude del flujo. Si alguien
        // robó la sesión y movió la cuenta, el dueño real se entera en su
        // bandeja de siempre y tiene la vía de recuperación.
        $this->send($oldEmail, new EmailChangedNoticeMail(
            $user->name,
            $oldEmail,
            $newEmail,
            route('password.request'),
        ));

        return $user;
    }

    /**
     * @throws ValidationException
     */
    private function rejectToken(): never
    {
        throw ValidationException::withMessages([
            'token' => __('Este enlace de confirmación no es válido.'),
        ]);
    }

    private function clearPendingEmail(User $user): void
    {
        $user->forceFill([
            'pending_email' => null,
            'pending_email_token' => null,
            'pending_email_requested_at' => null,
        ])->save();
    }

    private function hashToken(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }

    /**
     * Envía un correo transaccional sin romper el flujo (ADR-0015): un SMTP
     * caído no puede tumbar la acción del usuario. Log sin PII — solo la
     * clase del correo y el error.
     */
    private function send(string $to, Mailable $mail): void
    {
        if (! mail_is_deliverable()) {
            return;
        }

        try {
            Mail::to($to)->send($mail);
        } catch (Throwable $e) {
            Log::warning('No se pudo enviar un correo de gestión de cuenta.', [
                'mail' => $mail::class,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
