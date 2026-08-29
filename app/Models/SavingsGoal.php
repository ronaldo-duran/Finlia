<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SavingsGoalPriority;
use App\Enums\SavingsGoalStatus;
use Database\Factories\SavingsGoalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Meta de ahorro del hogar (Épica 7): fondo de emergencia, viaje, cuota
 * inicial de vivienda…
 *
 * El ahorrado NO se teclea: `current_amount` se recalcula desde los aportes
 * y retiros registrados (ADR-0025, espejo del saldo de deuda en ADR-0020).
 */
#[Fillable([
    'name', 'target_amount', 'target_date', 'priority',
    'monthly_commitment', 'is_emergency_fund', 'status', 'notes',
])]
class SavingsGoal extends Model
{
    /** @use HasFactory<SavingsGoalFactory> */
    use HasFactory;

    /**
     * household_id NO es fillable: lo asigna el controlador desde el hogar
     * activo. current_amount tampoco: es derivado (ADR-0025).
     */
    protected function casts(): array
    {
        return [
            'target_amount' => 'decimal:2',
            'current_amount' => 'decimal:2',
            'monthly_commitment' => 'decimal:2',
            'target_date' => 'date:Y-m-d',
            'priority' => SavingsGoalPriority::class,
            'status' => SavingsGoalStatus::class,
            'is_emergency_fund' => 'boolean',
        ];
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    public function contributions(): HasMany
    {
        return $this->hasMany(SavingsGoalContribution::class);
    }

    /**
     * Scope: metas que el hogar sigue gestionando (activas o pausadas). Es
     * el filtro del panel y del término `savings` del dinero disponible.
     */
    public function scopeOutstanding(Builder $query): Builder
    {
        return $query->whereIn('status', SavingsGoalStatus::outstandingValues());
    }

    /**
     * Cuánto de la meta está cubierto, en porcentaje sobre el objetivo.
     * Devuelve 0 si no hay objetivo (evita división por cero).
     */
    public function progressPercent(): float
    {
        $target = (float) $this->target_amount;

        if ($target <= 0.0) {
            return 0.0;
        }

        return round(min(100.0, (float) $this->current_amount / $target * 100), 1);
    }

    /**
     * Cuánto falta para llegar al objetivo. Nunca negativo: aportar de más
     * completa la meta, no genera "excedente" que confunda.
     */
    public function remainingAmount(): float
    {
        return round(max(0.0, (float) $this->target_amount - (float) $this->current_amount), 2);
    }

    /**
     * ¿La fecha objetivo ya pasó sin completar la meta?
     */
    public function isOverdue(): bool
    {
        return $this->target_date !== null
            && $this->status !== SavingsGoalStatus::Completed
            && $this->target_date->isPast();
    }
}
