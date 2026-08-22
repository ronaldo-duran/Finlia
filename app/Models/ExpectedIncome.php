<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ExpectedIncomeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ingreso mensual esperado del hogar (salario, arriendo, inversión…).
 * Alimenta el término "ingresos esperados" del dinero disponible (ADR-0014).
 */
#[Fillable(['category_id', 'name', 'amount', 'day_of_month', 'is_active', 'notes'])]
class ExpectedIncome extends Model
{
    /** @use HasFactory<ExpectedIncomeFactory> */
    use HasFactory;

    /**
     * household_id NO es fillable: lo asigna el controlador desde el hogar activo.
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'day_of_month' => 'integer',
            'is_active' => 'boolean',
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
     * Scope: solo los ingresos esperados que cuentan para el cálculo.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
