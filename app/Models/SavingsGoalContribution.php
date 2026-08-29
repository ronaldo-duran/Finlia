<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SavingsGoalContributionType;
use Database\Factories\SavingsGoalContributionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Movimiento sobre una meta de ahorro (Épica 7): aporte o retiro. Es la
 * fuente de verdad de `current_amount` (ADR-0025).
 *
 * NO mueve cuentas ni crea gastos: ahorrar no es gastar (ADR-0025); la
 * transferencia entre cuentas quedó diferida a la Épica 10.
 */
#[Fillable(['amount', 'date', 'type', 'notes'])]
class SavingsGoalContribution extends Model
{
    /** @use HasFactory<SavingsGoalContributionFactory> */
    use HasFactory;

    /**
     * household_id y savings_goal_id NO son fillable: los asigna
     * SavingsGoalService, nunca la petición.
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'date' => 'date:Y-m-d',
            'type' => SavingsGoalContributionType::class,
        ];
    }

    public function goal(): BelongsTo
    {
        return $this->belongsTo(SavingsGoal::class, 'savings_goal_id');
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }
}
