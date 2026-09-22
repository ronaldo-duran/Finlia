<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\HouseholdRole;
use App\Enums\InvitationStatus;
use App\Mail\AccountDeletionRequestedMail;
use App\Mail\OwnershipTransferredMail;
use App\Models\Household;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Eliminación de cuenta: suspensión 30 días, anonimización y cascade
 * solo para dueño único (Plan 05, ADR-0033).
 *
 * Nunca depende de la capa HTTP: recibe datos, devuelve resultados.
 */
class AccountDeletionService
{
    public const SUSPENSION_DAYS = 30;

    /**
     * Solicita la eliminación de la cuenta: marca la fecha de suspensión,
     * cancela invitaciones pendientes emitidas por el usuario y envía el
     * correo antifraude (Plan 05).
     *
     * NO cierra la sesión: el controlador lo hace tras llamar a este método,
     * igual que changePassword (ADR-0010).
     */
    public function requestDeletion(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $user->deletion_requested_at = now();
            $user->save();

            $user->ownedHouseholds()
                ->with('invitations')
                ->get()
                ->each(function (Household $household): void {
                    $household->invitations()
                        ->where('status', InvitationStatus::Pending->value)
                        ->update(['status' => InvitationStatus::Revoked->value]);
                });
        });

        if (mail_is_deliverable()) {
            try {
                Mail::to($user->email)->send(new AccountDeletionRequestedMail($user));
            } catch (Throwable $e) {
                Log::warning('No se pudo enviar el correo de confirmación de suspensión.', [
                    'user_id' => $user->getKey(),
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Cancela la suspensión (reactivación dentro de los 30 días).
     */
    public function cancelDeletion(User $user): void
    {
        $user->deletion_requested_at = null;
        $user->save();
    }

    /**
     * Purga un usuario cuya ventana de 30 días expiró.
     *
     * Aplica una de tres reglas según la relación del usuario con sus hogares:
     *  1. Miembro sin hogares propios, o con hogares compartidos → anonimizar.
     *  2. Dueño único (sin otros miembros activos) → borrar el hogar completo.
     *  3. Dueño con otros miembros → transferir ownership + anonimizar.
     *
     * Registra en el log de auditoría sin datos personales (ADR-0033).
     */
    public function purge(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $ownedHouseholds = Household::withTrashed()
                ->where('owner_id', $user->id)
                ->with(['members'])
                ->get();

            $reactivationCutoff = now()->subDays(self::SUSPENSION_DAYS);

            foreach ($ownedHouseholds as $household) {
                $others = $household->members->where('id', '!=', $user->id);

                $survivingMembers = $others->filter(
                    fn (User $m) => $m->deletion_requested_at === null
                        || $m->deletion_requested_at->greaterThanOrEqualTo($reactivationCutoff)
                );

                if ($survivingMembers->isEmpty()) {
                    $this->deleteHouseholdCascade($household);
                } else {
                    $activeMembers = $survivingMembers->where('deletion_requested_at', null);
                    $pool = $activeMembers->isNotEmpty() ? $activeMembers : $survivingMembers;

                    $newOwner = $pool->sortBy(
                        fn (User $m) => $m->pivot?->joined_at ?? $m->created_at
                    )->first();

                    $household->update(['owner_id' => $newOwner->id]);
                    $household->members()->updateExistingPivot($newOwner->id, ['role' => HouseholdRole::Owner->value]);

                    $household->members()->detach($user->id);

                    $this->notifyNewOwner($newOwner, $household);
                }
            }

            $user->households()->detach();

            $originalEmail = $user->email;

            $user->forceFill([
                'name' => 'Usuario eliminado',
                'email' => 'deleted+'.$user->id.'@finlia.invalid',
                'password' => bcrypt(str()->random(64)),
                'birth_date' => null,
                'region' => null,
                'gender' => null,
                'pending_email' => null,
                'pending_email_token' => null,
                'pending_email_requested_at' => null,
                'deletion_requested_at' => null,
                'email_verified_at' => null,
            ])->save();

            DB::table('sessions')->where('user_id', $user->id)->delete();
            DB::table('password_reset_tokens')->where('email', $originalEmail)->delete();

            Log::info('Cuenta purgada.', [
                'user_id' => $user->id,
                'rule' => $ownedHouseholds->isEmpty() ? 'member_only' : 'owner_handled',
                'owned_households' => $ownedHouseholds->count(),
                'purged_at' => now()->toIso8601String(),
            ]);
        });
    }

    /**
     * Purga una cuenta fantasma: registrada pero nunca verificada.
     * Cascade total — sin datos financieros verificados (Plan 05).
     */
    public function purgeUnverified(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $user->households()->get()->each(
                fn (Household $h) => $this->deleteHouseholdCascade($h)
            );

            $user->delete();

            Log::info('Cuenta fantasma purgada (sin verificar).', [
                'user_id' => $user->id,
                'created_at' => $user->created_at?->toIso8601String(),
                'purged_at' => now()->toIso8601String(),
            ]);
        });
    }

    /**
     * Borra un hogar y todo su contenido en cascada.
     * Solo se llama cuando no hay otros miembros activos (Regla 2).
     */
    private function deleteHouseholdCascade(Household $household): void
    {
        $household->members()->detach();
        $household->forceDelete();
    }

    private function notifyNewOwner(User $newOwner, Household $household): void
    {
        if (! mail_is_deliverable() || ! $newOwner->email_verified_at) {
            return;
        }

        try {
            Mail::to($newOwner->email)->send(new OwnershipTransferredMail($newOwner, $household));
        } catch (Throwable $e) {
            Log::warning('No se pudo notificar al nuevo owner del hogar.', [
                'user_id' => $newOwner->id,
                'household_id' => $household->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
