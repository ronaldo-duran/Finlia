<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\AcknowledgementKey;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
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
            'password' => 'hashed',
        ];
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
     * Hogares a los que pertenece el usuario (multi-hogar permitido).
     */
    public function households(): BelongsToMany
    {
        return $this->belongsToMany(Household::class, 'household_user')
            ->using(HouseholdMember::class)
            ->withPivot(['role', 'joined_at'])
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
