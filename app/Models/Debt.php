<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DebtStatus;
use App\Enums\DebtType;
use App\Enums\InterestRateType;
use Database\Factories\DebtFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Deuda del hogar (Épica 6): tarjeta, préstamo, crédito de vehículo…
 *
 * El saldo NO se teclea: `current_balance` se recalcula desde la línea base
 * menos los pagos (ADR-0020). Ver DebtService::recalculateBalance().
 */
#[Fillable([
    'name', 'institution', 'type', 'original_amount', 'interest_rate',
    'interest_rate_type', 'minimum_payment', 'scheduled_payment', 'due_day',
    'start_date', 'end_date', 'status', 'notes', 'account_id',
])]
class Debt extends Model
{
    /** @use HasFactory<DebtFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * household_id NO es fillable: lo asigna el controlador desde el hogar
     * activo. current_balance tampoco: es derivado (ADR-0020).
     */
    protected function casts(): array
    {
        return [
            'original_amount' => 'decimal:2',
            'current_balance' => 'decimal:2',
            'interest_rate' => 'decimal:3',
            'minimum_payment' => 'decimal:2',
            'scheduled_payment' => 'decimal:2',
            'type' => DebtType::class,
            'status' => DebtStatus::class,
            'interest_rate_type' => InterestRateType::class,
            'due_day' => 'integer',
            // date:Y-m-d — sin él el grammar serializa con hora y SQLite
            // guarda "2026-09-05 00:00:00" en una columna DATE.
            'start_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
        ];
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    /** Cuenta asociada cuando la deuda es una tarjeta (ADR-0002). */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(DebtPayment::class);
    }

    public function refinancings(): HasMany
    {
        return $this->hasMany(DebtRefinancing::class);
    }

    /** La refinanciación más reciente marca la línea base del saldo (ADR-0020). */
    public function latestRefinancing(): HasOne
    {
        return $this->hasOne(DebtRefinancing::class)->latestOfMany('start_date');
    }

    /**
     * Scope: deudas que aún pesan (activas o refinanciadas). Es el filtro del
     * panel de deuda y del término `debt` del dinero disponible.
     */
    public function scopeOutstanding(Builder $query): Builder
    {
        return $query->whereIn('status', DebtStatus::outstandingValues());
    }

    /**
     * Cuánto se ha amortizado ya, en porcentaje sobre la deuda original.
     * Devuelve 0 si no hay importe original (evita división por cero).
     */
    public function progressPercent(): float
    {
        $original = (float) $this->original_amount;

        if ($original <= 0.0) {
            return 0.0;
        }

        $paid = $original - (float) $this->current_balance;

        return round(max(0.0, min(100.0, $paid / $original * 100)), 1);
    }

    /** La cuota que el hogar se comprometió a pagar cada mes. */
    public function monthlyCommitment(): float
    {
        return (float) ($this->scheduled_payment ?? $this->minimum_payment ?? 0);
    }
}
