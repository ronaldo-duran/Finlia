<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\HouseholdRole;
use Database\Factories\HouseholdFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'owner_id', 'currency', 'timezone'])]
class Household extends Model
{
    /** @use HasFactory<HouseholdFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Creador / administrador del hogar.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Usuarios miembros del hogar (relación multi-hogar por usuario).
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'household_user')
            ->using(HouseholdMember::class)
            ->withPivot(['role', 'joined_at'])
            ->withTimestamps()
            ->orderByPivot('joined_at');
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(HouseholdInvitation::class);
    }

    // ---- Épica 3: finanzas del hogar ----

    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function incomes(): HasMany
    {
        return $this->hasMany(Income::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    // ---- Épica 4: presupuestos y dinero disponible ----

    public function budgets(): HasMany
    {
        return $this->hasMany(Budget::class);
    }

    public function expectedIncomes(): HasMany
    {
        return $this->hasMany(ExpectedIncome::class);
    }

    // ---- Épica 5: gastos recurrentes y obligaciones futuras ----

    public function recurringExpenses(): HasMany
    {
        return $this->hasMany(RecurringExpense::class);
    }

    // ---- Épica 6: deudas y tarjetas de crédito ----

    public function debts(): HasMany
    {
        return $this->hasMany(Debt::class);
    }

    public function debtPayments(): HasMany
    {
        return $this->hasMany(DebtPayment::class);
    }

    public function creditCards(): HasMany
    {
        return $this->hasMany(CreditCard::class);
    }

    // ---- Épica 7: metas de ahorro ----

    public function savingsGoals(): HasMany
    {
        return $this->hasMany(SavingsGoal::class);
    }

    public function savingsGoalContributions(): HasMany
    {
        return $this->hasMany(SavingsGoalContribution::class);
    }

    /**
     * Indica si un usuario es miembro del hogar.
     */
    public function hasMember(User $user): bool
    {
        if ($this->relationLoaded('members')) {
            return $this->members->contains(fn (User $m) => $m->is($user));
        }

        return $this->members()->where('user_id', $user->id)->exists();
    }

    /**
     * Rol del usuario en el hogar (null si no es miembro).
     */
    public function roleOf(User $user): ?HouseholdRole
    {
        if ($this->owner_id === $user->id) {
            return HouseholdRole::Owner;
        }

        $role = $this->members()->where('user_id', $user->id)->value('role');

        return $role !== null ? HouseholdRole::tryFrom($role) : null;
    }
}
