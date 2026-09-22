<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ReceivableStatus;
use Database\Factories\ReceivableFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Cuenta por cobrar del hogar (Épica 15). El saldo `current_balance` NO se
 * teclea: se recalcula desde `original_amount` menos los cobros
 * registrados (ADR-0020 espejo). Ver ReceivableService::recomputeBalance().
 */
#[Fillable([
    'debtor_name', 'debtor_user_id', 'name', 'description',
    'original_amount', 'currency', 'status', 'due_date', 'notes',
])]
class Receivable extends Model
{
    /** @use HasFactory<ReceivableFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * household_id NO es fillable: lo asigna el controlador desde el hogar
     * activo. current_balance tampoco: es derivado (ADR-0020 espejo).
     */
    protected function casts(): array
    {
        return [
            'original_amount' => 'decimal:2',
            'current_balance' => 'decimal:2',
            'status' => ReceivableStatus::class,
            'due_date' => 'date:Y-m-d',
        ];
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    /** Deudor si es miembro del hogar; texto libre en caso contrario. */
    public function debtorUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'debtor_user_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(ReceivablePayment::class);
    }

    /**
     * Scope: cuentas por cobrar que aún pesan (pendientes o parciales). Es
     * el filtro del panel y de la tarjeta del dashboard.
     */
    public function scopeOutstanding(Builder $query): Builder
    {
        return $query->whereIn('status', ReceivableStatus::outstandingValues());
    }

    /**
     * Cuánto se ha cobrado, en porcentaje sobre el importe original.
     * Devuelve 0 si no hay importe original (evita división por cero).
     */
    public function progressPercent(): float
    {
        $original = (float) $this->original_amount;

        if ($original <= 0.0) {
            return 0.0;
        }

        $collected = $original - (float) $this->current_balance;

        return round(max(0.0, min(100.0, $collected / $original * 100)), 1);
    }
}
