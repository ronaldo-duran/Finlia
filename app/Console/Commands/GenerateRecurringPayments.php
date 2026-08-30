<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\RecurringExpense;
use App\Services\RecurringExpenseService;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Generación automática de gastos recurrentes (Épica 9, ADR-0018).
 *
 * Lo ejecuta el Scheduler a diario (cron de Hostinger → schedule:run,
 * sin workers persistentes). Por cada recurrente activo con
 * `auto_generate` y `next_date` vencida, registra el pago con la rutina
 * de siempre (markAsPaid): gasto real con su cuenta + fecha de avance.
 *
 * Una corrida regulariza UNA ocurrencia por recurrente — la más vencida,
 * con su fecha real. Un atraso de N días se recupera en N corridas: nunca
 * dispara una ráfaga de gastos todos fechados "hoy".
 */
class GenerateRecurringPayments extends Command
{
    protected $signature = 'finlia:generate-recurring-payments';

    protected $description = 'Registra los pagos vencidos de recurrentes con generación automática (Épica 9, ADR-0018)';

    public function handle(RecurringExpenseService $recurring): int
    {
        $today = Carbon::now(config('app.timezone'))->startOfDay();

        $due = RecurringExpense::query()
            ->where('auto_generate', true)
            ->where('is_active', true)
            ->whereDate('next_date', '<=', $today->toDateString())
            ->with('household.owner')
            ->orderBy('next_date')
            ->get();

        if ($due->isEmpty()) {
            $this->info('Sin obligaciones vencidas con generación automática.');

            return self::SUCCESS;
        }

        foreach ($due as $expense) {
            // El gasto queda a nombre de quien administra el hogar: un pago
            // automático no tiene usuario detrás.
            $owner = $expense->household->owner;

            $created = $recurring->markAsPaid($expense, $owner, $expense->next_date);

            $this->info(sprintf(
                '%s (%s): %s — próxima fecha %s.',
                $expense->name,
                $expense->household->name,
                $created !== null ? 'gasto registrado por '.$created->amount : 'sin cuenta, solo avanza la fecha',
                $expense->next_date->format('d/m/Y'),
            ));
        }

        return self::SUCCESS;
    }
}
