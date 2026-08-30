<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Household;
use App\Services\ReminderService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Invalida el resumen cacheado de recordatorios del hogar cuando cambia
 * cualquier dato que lo alimenta (Épica 9).
 *
 * El conteo de la campanita se cachea porque corre en TODAS las páginas
 * autenticadas (view composer del navbar). El TTL de la clave es solo red
 * de seguridad — p. ej. el paso de medianoche que vence avisos — porque
 * toda mutación que altera el conteo pasa por uno de estos modelos y
 * dispara su evento de Eloquent, que borra la clave al instante.
 *
 * Se registra en AppServiceProvider para: Debt, DebtPayment, Household,
 * RecurringExpense, Reminder, SavingsGoal y SavingsGoalContribution.
 */
class ReminderSummaryCacheObserver
{
    public function created(Model $model): void
    {
        $this->invalidate($model);
    }

    public function updated(Model $model): void
    {
        $this->invalidate($model);
    }

    public function deleted(Model $model): void
    {
        $this->invalidate($model);
    }

    /** Debt usa SoftDeletes: restaurar o borrar definitivo también cuenta. */
    public function restored(Model $model): void
    {
        $this->invalidate($model);
    }

    public function forceDeleted(Model $model): void
    {
        $this->invalidate($model);
    }

    private function invalidate(Model $model): void
    {
        // Household es la excepción: su PK ES el hogar, no tiene household_id.
        $householdId = $model instanceof Household ? $model->id : $model->household_id;

        if ($householdId !== null) {
            Cache::forget(ReminderService::summaryCacheKey((int) $householdId));
        }
    }
}
