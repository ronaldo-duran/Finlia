<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Expense;
use App\Models\RecurringExpense;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Lógica de los gastos recurrentes y obligaciones futuras (Épica 5):
 * próximas obligaciones, alertas de vencimiento, ahorro mensual necesario
 * y el término que alimenta el dinero disponible (ADR-0014).
 *
 * Seam (ADR-0010): no depende de la capa HTTP. Recibe IDs y fechas
 * explícitos y devuelve arrays/colecciones serializables.
 */
class RecurringExpenseService
{
    /** Días de antelación de los avisos ("El SOAT vence en 30 días"). */
    public const ALERT_WINDOW_DAYS = 30;

    public function __construct(private readonly MovementService $movements) {}

    /**
     * Próximas obligaciones del hogar: cada recurrente activo enriquecido con
     * días restantes, ahorro mensual recomendado y estado de vencimiento.
     * Ordenadas por próxima fecha (las vencidas primero, que son las urgentes).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function upcoming(int $householdId, ?CarbonInterface $reference = null): Collection
    {
        $today = $this->today($reference);

        return RecurringExpense::where('household_id', $householdId)
            ->active()
            ->with(['category', 'account'])
            ->orderBy('next_date')
            ->get()
            ->map(fn (RecurringExpense $recurring) => [
                'id' => $recurring->id,
                'name' => $recurring->name,
                'amount' => (float) $recurring->amount,
                'frequency' => $recurring->frequency,
                'frequency_label' => $recurring->frequency->shortLabel($recurring->frequency_interval),
                'next_date' => $recurring->next_date,
                'days_remaining' => $days = (int) round($today->diffInDays($recurring->next_date)),
                'is_overdue' => $days < 0,
                'monthly_savings' => $this->monthlySavings($recurring),
                'category_name' => $recurring->category?->name,
                'category_color' => $recurring->category?->color,
                'account_id' => $recurring->account_id,
                'has_account' => $recurring->account_id !== null,
            ])
            ->values();
    }

    /**
     * Alertas de vencimiento: obligaciones vencidas o que vencen dentro de
     * la ventana de aviso (self::ALERT_WINDOW_DAYS).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function alerts(int $householdId, ?CarbonInterface $reference = null): Collection
    {
        return $this->upcoming($householdId, $reference)
            ->filter(fn (array $item) => $item['days_remaining'] <= self::ALERT_WINDOW_DAYS)
            ->values();
    }

    /**
     * Ahorro mensual recomendado para afrontar la obligación sin sorpresa
     * (SOAT de $600.000 anual → separar $50.000 mensuales).
     */
    public function monthlySavings(RecurringExpense $recurring): float
    {
        $perYear = $recurring->frequency->occurrencesPerYear($recurring->frequency_interval);

        return round((float) $recurring->amount * $perYear / 12, 2);
    }

    /**
     * Total mensual que el hogar debería separar por todos sus recurrentes activos.
     */
    public function totalMonthlySavings(int $householdId): float
    {
        return RecurringExpense::where('household_id', $householdId)
            ->active()
            ->get()
            ->sum(fn (RecurringExpense $recurring) => $this->monthlySavings($recurring));
    }

    /**
     * Dinero comprometido por recurrentes dentro de una ventana [from, to],
     * separado en las dos claves que alimenta el calculador (ADR-0014):
     *
     *  - fixed    : gastos de alta frecuencia (semana/quincena/mes): arriendo,
     *               internet, servicios…
     *  - recurring: obligaciones menos frecuentes (trimestral+): SOAT,
     *               matrícula, seguro…
     *
     * Solo cuenta ocurrencias aún pendientes: al "Marcar pagado" la fecha
     * avanza y la ocurrencia sale de la ventana (no se duplica con el gasto
     * ya registrado).
     *
     * @return array{fixed: float, recurring: float, total: float}
     */
    public function committedInRange(int $householdId, CarbonInterface $from, CarbonInterface $to): array
    {
        $from = Carbon::parse($from)->startOfDay();
        $to = Carbon::parse($to)->startOfDay();

        $fixed = 0.0;
        $recurring = 0.0;

        RecurringExpense::where('household_id', $householdId)
            ->active()
            ->get()
            ->each(function (RecurringExpense $recurringExpense) use (&$fixed, &$recurring, $from, $to): void {
                $amount = (float) $recurringExpense->amount
                    * $this->occurrencesInWindow($recurringExpense, $from, $to);

                if ($recurringExpense->frequency->isFixedLike($recurringExpense->frequency_interval)) {
                    $fixed += $amount;
                } else {
                    $recurring += $amount;
                }
            });

        $fixed = round($fixed, 2);
        $recurring = round($recurring, 2);

        return ['fixed' => $fixed, 'recurring' => $recurring, 'total' => round($fixed + $recurring, 2)];
    }

    /**
     * Cuántas veces ocurre un recurrente dentro de la ventana, simulando
     * desde su próxima fecha. Un gasto semanal cuenta 4-5 veces en un mes;
     * una obligación cuya próxima fecha ya pasó cuenta su siguiente
     * ocurrencia real dentro de la ventana (la ocurrencia vencida se
     * regulariza con "Marcar pagado").
     *
     * Techo defensivo de saltos para un intervalo personalizado degenerado.
     */
    public function occurrencesInWindow(RecurringExpense $recurring, CarbonInterface $from, CarbonInterface $to): int
    {
        $cursor = Carbon::parse($recurring->next_date)->startOfDay();
        $from = Carbon::parse($from)->startOfDay();
        $to = Carbon::parse($to)->startOfDay();

        if ($cursor->gt($to)) {
            return 0;
        }

        $count = 0;

        for ($jumps = 0; $jumps < 1000; $jumps++) {
            if ($cursor->between($from, $to)) {
                $count++;
            }

            if ($cursor->gt($to)) {
                break;
            }

            $cursor = $recurring->frequency->advance($cursor, $recurring->frequency_interval);
        }

        return $count;
    }

    /**
     * Marca una obligación como pagada: registra el gasto real (con la
     * categoría y cuenta del recurrente, si las tiene) y avanza la próxima
     * fecha a su siguiente ocurrencia. Todo en una transacción para que el
     * saldo de la cuenta quede coherente (ADR-0012).
     *
     * Si el recurrente no tiene cuenta asociada no se puede registrar el
     * gasto (expenses.account_id es obligatorio): solo avanza la fecha y
     * devuelve null. El usuario registra el pago a mano desde /gastos.
     */
    public function markAsPaid(
        RecurringExpense $recurring,
        User $user,
        ?CarbonInterface $paidAt = null,
    ): ?Expense {
        return DB::transaction(function () use ($recurring, $user, $paidAt): ?Expense {
            $expense = null;
            $date = Carbon::parse($paidAt ?? Carbon::now(config('app.timezone')))->toDateString();

            if ($recurring->account_id !== null) {
                $expense = $this->movements->createExpense([
                    'account_id' => $recurring->account_id,
                    'category_id' => $recurring->category_id,
                    'amount' => $recurring->amount,
                    'date' => $date,
                    'description' => $recurring->name.' (pago recurrente)',
                    'notes' => $recurring->notes,
                ], $recurring->household, $user);
            }

            $recurring->forceFill([
                'next_date' => $recurring->frequency
                    ->advance($recurring->next_date, $recurring->frequency_interval)
                    ->toDateString(),
            ])->save();

            return $expense;
        });
    }

    /**
     * Hoy (o la fecha de referencia de tests) al inicio del día, en la zona
     * del hogar (America/Bogota).
     */
    private function today(?CarbonInterface $reference): Carbon
    {
        return ($reference !== null
            ? Carbon::parse($reference)
            : Carbon::now(config('app.timezone')))
            ->startOfDay();
    }
}
