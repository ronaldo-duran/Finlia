<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\HouseholdRole;
use App\Enums\InvitationStatus;
use App\Mail\HouseholdInvitationMail;
use App\Models\Household;
use App\Models\HouseholdInvitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Support\Stringable;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Lógica de dominio sobre hogares, miembros e invitaciones.
 *
 * Seam (ADR-0010): NO depende de la capa HTTP. Recibe datos explícitos
 * (IDs, modelos) y devuelve resultados. El controlador se encarga de
 * sesión/autorización. Así una futura API (Épica 14) puede reusar esto.
 */
class HouseholdService
{
    private const INVITATION_TTL_DAYS = 7;

    private const TOKEN_LENGTH = 64;

    /**
     * Crea un hogar y vincula al creador como administrador (owner).
     */
    public function createHousehold(
        int $ownerId,
        string $name,
        string $currency = 'COP',
        string $timezone = 'America/Bogota',
    ): Household {
        return DB::transaction(function () use ($ownerId, $name, $currency, $timezone): Household {
            $household = Household::create([
                'name' => $name,
                'owner_id' => $ownerId,
                'currency' => $currency,
                'timezone' => $timezone,
            ]);

            $household->members()->attach($ownerId, [
                'role' => HouseholdRole::Owner->value,
                'joined_at' => now(),
            ]);

            return $household;
        });
    }

    /**
     * Actualiza los datos editables del hogar.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateHousehold(Household $household, array $data): Household
    {
        $household->fill([
            'name' => $data['name'] ?? $household->name,
            'currency' => $data['currency'] ?? $household->currency,
            'timezone' => $data['timezone'] ?? $household->timezone,
        ])->save();

        return $household;
    }

    /**
     * Genera una invitación para un correo y se la envía al invitado.
     *
     * Devuelve [invitación, token_plano, correo_enviado]: el token plano viaja
     * SOLO en el correo y en el enlace que ve el owner; en BD se guarda el
     * hash (ADR-0003). El tercer elemento indica si el correo salió de verdad;
     * si es `false` el owner debe compartir el enlace a mano.
     *
     * `$invitedByName` llega como dato explícito desde el controlador para no
     * acoplar el Service a la capa HTTP (ADR-0010).
     *
     * @return array{0: HouseholdInvitation, 1: string, 2: bool}
     *
     * @throws ValidationException
     */
    public function inviteMember(
        Household $household,
        string $email,
        HouseholdRole $role,
        ?string $invitedByName = null,
    ): array {
        $email = str($email)->lower()->trim()->toString();

        $existingUser = User::firstWhere('email', $email);
        if ($existingUser !== null && $household->hasMember($existingUser)) {
            throw ValidationException::withMessages([
                'email' => __('Ese usuario ya es miembro del hogar.'),
            ]);
        }

        // Revoca invitaciones previas pendientes para el mismo correo.
        $household->invitations()
            ->where('email', $email)
            ->where('status', InvitationStatus::Pending->value)
            ->update(['status' => InvitationStatus::Revoked->value]);

        $plainToken = $this->generatePlainToken();

        $invitation = HouseholdInvitation::create([
            'household_id' => $household->id,
            'email' => $email,
            'token' => $this->hashToken($plainToken),
            'role' => $role,
            'status' => InvitationStatus::Pending,
            'expires_at' => now()->addDays(self::INVITATION_TTL_DAYS),
        ]);

        // Evita una consulta extra al construir el correo.
        $invitation->setRelation('household', $household);

        $emailSent = $this->sendInvitationEmail($invitation, $plainToken, $invitedByName);

        return [$invitation, $plainToken, $emailSent];
    }

    /**
     * Envía el correo de invitación (ADR-0015).
     *
     * El correo es un extra, no el mecanismo: si falla el SMTP o no está
     * configurado, la invitación ya quedó creada y el enlace manual sigue
     * siendo válido. Por eso nunca propaga la excepción.
     */
    private function sendInvitationEmail(
        HouseholdInvitation $invitation,
        string $plainToken,
        ?string $invitedByName,
    ): bool {
        if (! mail_is_deliverable()) {
            return false;
        }

        try {
            Mail::to($invitation->email)->send(
                new HouseholdInvitationMail($invitation, $plainToken, $invitedByName)
            );
        } catch (Throwable $e) {
            // Nunca se loguea el token ni el enlace (docs/SECURITY.md §4).
            Log::warning('No se pudo enviar la invitación por correo.', [
                'household_id' => $invitation->household_id,
                'invitation_id' => $invitation->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }

        return true;
    }

    /**
     * Busca una invitación por su token público (lo hashea antes de comparar).
     */
    public function findInvitationByPlainToken(string $plainToken): ?HouseholdInvitation
    {
        /** @var Stringable $normalized */
        $normalized = str($plainToken)->trim();

        if ($normalized->length() !== self::TOKEN_LENGTH) {
            return null;
        }

        return HouseholdInvitation::where('token', $this->hashToken($normalized->toString()))->first();
    }

    /**
     * Acepta una invitación: valida estado, expiración y coincidencia de correo,
     * vincula al usuario y marca la invitación como aceptada.
     *
     * @throws ValidationException
     */
    public function acceptInvitation(HouseholdInvitation $invitation, User $user): Household
    {
        $this->ensureAcceptable($invitation, $user);

        return DB::transaction(function () use ($invitation, $user): Household {
            $household = $invitation->household;

            $household->members()->attach($user->id, [
                'role' => $invitation->role->value,
                'joined_at' => now(),
            ]);

            $invitation->forceFill([
                'status' => InvitationStatus::Accepted,
                'accepted_at' => now(),
                'accepted_by_user_id' => $user->id,
            ])->save();

            return $household;
        });
    }

    public function revokeInvitation(HouseholdInvitation $invitation): void
    {
        $invitation->update(['status' => InvitationStatus::Revoked]);
    }

    /**
     * Expulsa a un miembro del hogar (nunca al administrador).
     *
     * @throws ValidationException
     */
    public function removeMember(Household $household, User $member): void
    {
        if ($household->owner_id === $member->id) {
            throw ValidationException::withMessages([
                'member' => __('No se puede eliminar al administrador del hogar.'),
            ]);
        }

        $household->members()->detach($member->id);
    }

    /**
     * @throws ValidationException
     */
    private function ensureAcceptable(HouseholdInvitation $invitation, User $user): void
    {
        if (! $invitation->isPending()) {
            throw ValidationException::withMessages([
                'token' => __('Esta invitación ya no está disponible.'),
            ]);
        }

        if ($invitation->isExpired()) {
            $invitation->update(['status' => InvitationStatus::Expired]);

            throw ValidationException::withMessages([
                'token' => __('Esta invitación ha expirado.'),
            ]);
        }

        if (strtolower((string) $invitation->email) !== strtolower((string) $user->email)) {
            throw ValidationException::withMessages([
                'token' => __('Esta invitación no corresponde a tu correo.'),
            ]);
        }

        if ($invitation->household->hasMember($user)) {
            throw ValidationException::withMessages([
                'token' => __('Ya eres miembro de este hogar.'),
            ]);
        }
    }

    private function generatePlainToken(): string
    {
        return Str::random(self::TOKEN_LENGTH);
    }

    private function hashToken(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }
}
