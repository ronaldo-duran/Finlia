<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\DebtRefinancingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Refinanciación de una deuda (Épica 6): nuevas condiciones y nueva línea
 * base del saldo (ADR-0020). Los pagos anteriores a `start_date` ya están
 * incorporados en `refinanced_balance` y no se vuelven a restar.
 */
#[Fillable(['refinanced_balance', 'interest_rate', 'term_months', 'installment', 'start_date', 'notes'])]
class DebtRefinancing extends Model
{
    /** @use HasFactory<DebtRefinancingFactory> */
    use HasFactory;

    /** household_id y debt_id NO son fillable: los asigna DebtService. */
    protected function casts(): array
    {
        return [
            'refinanced_balance' => 'decimal:2',
            'interest_rate' => 'decimal:3',
            'installment' => 'decimal:2',
            'term_months' => 'integer',
            'start_date' => 'date:Y-m-d',
        ];
    }

    public function debt(): BelongsTo
    {
        return $this->belongsTo(Debt::class);
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }
}
