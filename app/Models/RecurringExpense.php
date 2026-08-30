<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Frequency;
use Database\Factories\RecurringExpenseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Gasto recurrente u obligación futura del hogar (Épica 5): SOAT, arriendo,
 * suscripciones… Alimenta las claves fixed_expenses/recurring del cálculo
 * de dinero disponible (ADR-0014).
 */
#[Fillable(['name', 'amount', 'frequency', 'frequency_interval', 'next_date', 'category_id', 'account_id', 'is_active', 'auto_generate', 'notes'])]
class RecurringExpense extends Model
{
    /** @use HasFactory<RecurringExpenseFactory> */
    use HasFactory;

    /**
     * household_id NO es fillable: lo asigna el controlador desde el hogar activo.
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'frequency' => Frequency::class,
            'frequency_interval' => 'integer',
            // date:Y-m-d — sin él, el grammar serializa con hora y SQLite
            // guarda "2026-09-05 00:00:00" en una columna DATE.
            'next_date' => 'date:Y-m-d',
            'is_active' => 'boolean',
            'auto_generate' => 'boolean', // Épica 9 (ADR-0018): pago automático vía Scheduler
        ];
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Scope: solo los recurrentes que cuentan para el cálculo y los avisos.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
