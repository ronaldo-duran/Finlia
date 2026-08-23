<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BudgetPeriod;
use Database\Factories\BudgetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Presupuesto de un hogar para un mes concreto.
 * `category_id` NULL representa el presupuesto TOTAL del mes.
 */
#[Fillable(['category_id', 'amount', 'period', 'year', 'month'])]
class Budget extends Model
{
    /** @use HasFactory<BudgetFactory> */
    use HasFactory;

    /**
     * household_id NO es fillable: lo asigna el controlador desde el hogar
     * activo (`active_household()->budgets()->create(...)`).
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'period' => BudgetPeriod::class,
            'year' => 'integer',
            'month' => 'integer',
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

    /**
     * ¿Es el presupuesto total del mes (sin categoría)?
     */
    public function isTotal(): bool
    {
        return $this->category_id === null;
    }

    /**
     * Scope: presupuestos de un mes concreto.
     */
    public function scopeForMonth(Builder $query, int $year, int $month): Builder
    {
        return $query->where('year', $year)->where('month', $month);
    }
}
