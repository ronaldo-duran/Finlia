<?php

namespace App\Models;

use App\Enums\AcknowledgementKey;
use App\Mail\VerifyEmailMail;
use Carbon\Carbon;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Throwable;

#[Fillable(['name', 'email', 'password', 'birth_date', 'region', 'gender'])]
#[Hidden(['password', 'remember_token', 'pending_email_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'pending_email_requested_at' => 'datetime',
            'deletion_requested_at' => 'datetime',
            'password' => 'hashed',
            'birth_date' => 'date:Y-m-d',
        ];
    }

    /**
     * La cuenta está en proceso de eliminación (Plan 05, ADR-0033).
     * Null = cuenta activa; timestamp = suspendida, purga a los 30 días.
     */
    public function isSuspended(): bool
    {
        return $this->deletion_requested_at !== null;
    }

    /**
     * Fecha límite de purga (30 días desde la solicitud), o null si activa.
     */
    public function deletionDeadline(): ?Carbon
    {
        return $this->deletion_requested_at?->copy()->addDays(30);
    }

    /**
     * Avisos que el usuario ya dio por leídos (ADR-0024).
     */
    public function acknowledgements(): HasMany
    {
        return $this->hasMany(UserAcknowledgement::class);
    }

    /**
     * ¿Ya leyó este aviso? Usa la relación si está cargada, para no lanzar
     * una consulta por cada componente que pregunte en la misma página.
     */
    public function hasAcknowledged(AcknowledgementKey $key): bool
    {
        if ($this->relationLoaded('acknowledgements')) {
            return $this->acknowledgements->contains('key', $key);
        }

        return $this->acknowledgements()->where('key', $key->value)->exists();
    }

    /**
     * Marca un aviso como leído. Idempotente: pulsar dos veces «Entendido»
     * no crea dos filas ni mueve la fecha original.
     */
    public function acknowledge(AcknowledgementKey $key): void
    {
        $this->acknowledgements()->firstOrCreate(
            ['key' => $key->value],
            ['acknowledged_at' => now()],
        );

        $this->unsetRelation('acknowledgements');
    }

    /**
     * Envía el correo de verificación de correo (Plan 01, ADR-0029).
     *
     * Sustituye la notificación nativa (markdown en inglés) por un Mailable
     * con el estilo de Finlia, patrón de la invitación y el digest (ADR-0015).
     * Enlace firmado con expiración estándar de Laravel (60 min).
     *
     * Falla en silencio (log sin PII): un SMTP caído no debe romper el
     * registro — la pantalla de aviso tiene botón de reenvío como recuperación.
     * La base ya trae hasVerifiedEmail()/markEmailAsVerified() del trait
     * Illuminate\Auth\MustVerifyEmail del Authenticatable.
     */
    public function sendEmailVerificationNotification(): void
    {
        if (! mail_is_deliverable()) {
            return;
        }

        $expiresAt = now()->addMinutes((int) config('auth.verification.expire', 60));

        try {
            Mail::to($this->email)->send(new VerifyEmailMail(
                $this,
                URL::temporarySignedRoute(
                    'verification.verify',
                    $expiresAt,
                    ['id' => $this->getKey(), 'hash' => sha1($this->getEmailForVerification())],
                ),
                $expiresAt,
            ));
        } catch (Throwable $e) {
            Log::warning('No se pudo enviar el correo de verificación.', [
                'user_id' => $this->getKey(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Términos y condiciones que el usuario ha aceptado (Plan 03, ADR-0031).
     */
    public function acceptedTerms(): HasMany
    {
        return $this->hasMany(UserTermsAcceptance::class);
    }

    /**
     * ¿Ya aceptó la versión vigente? Sin versión publicada no hay
     * obligación: la app se usa normal (fail-open, ADR-0031).
     */
    public function hasAcceptedCurrentTerms(): bool
    {
        $current = TermsVersion::current();

        return $current === null
            || $this->acceptedTerms()->where('terms_version_id', $current->getKey())->exists();
    }

    /**
     * Registra la aceptación de una versión. Idempotente: re-aceptar no crea
     * una fila nueva ni mueve la fecha original (patrón de acknowledge()).
     */
    public function acceptTerms(TermsVersion $version, ?string $ipAddress = null): UserTermsAcceptance
    {
        return $this->acceptedTerms()->firstOrCreate(
            ['terms_version_id' => $version->getKey()],
            ['accepted_at' => now(), 'ip_address' => $ipAddress],
        );
    }

    /**
     * Edad en años, calculada desde birth_date (Plan 04, ADR-0032). Nunca
     * se almacena: lo derivable no ocupa columna. NULL sin birth_date
     * (usuarios heredados).
     */
    public function age(): ?int
    {
        // Propiedad ->age (getter de Carbon 3: el método ->age() ya no existe).
        return $this->birth_date?->age;
    }

    /**
     * Hogares a los que pertenece el usuario (multi-hogar permitido).
     */
    public function households(): BelongsToMany
    {
        return $this->belongsToMany(Household::class, 'household_user')
            ->using(HouseholdMember::class)
            ->withPivot(['role', 'joined_at', 'reminders_email', 'last_reminder_digest_at'])
            ->withTimestamps()
            ->orderByPivot('joined_at');
    }

    /**
     * Hogares de los que el usuario es administrador (owner).
     */
    public function ownedHouseholds(): HasMany
    {
        return $this->hasMany(Household::class, 'owner_id');
    }

    // ---- Épica 3: movimientos registrados por el usuario ----

    public function incomes(): HasMany
    {
        return $this->hasMany(Income::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }
}
