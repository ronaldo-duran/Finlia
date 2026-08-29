<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AccountType;
use Database\Factories\AccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['name', 'type', 'initial_balance', 'currency', 'is_active', 'notes'])]
class Account extends Model
{
    /** @use HasFactory<AccountFactory> */
    use HasFactory;

    /**
     * current_balance NO es fillable: lo gestiona AccountBalanceService (ADR-0012).
     * household_id tampoco: lo asigna el controlador desde el hogar activo.
     */
    protected function casts(): array
    {
        return [
            'type' => AccountType::class,
            'initial_balance' => 'decimal:2',
            'current_balance' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    public function incomes(): HasMany
    {
        return $this->hasMany(Income::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    /**
     * Atributos de tarjeta, solo si type=credit_card (Épica 6, ADR-0002).
     */
    public function creditCard(): HasOne
    {
        return $this->hasOne(CreditCard::class);
    }

    /**
     * Deudas asociadas a esta cuenta (típicamente la de la propia tarjeta).
     */
    public function debts(): HasMany
    {
        return $this->hasMany(Debt::class);
    }
}
