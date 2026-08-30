<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ReminderSource;
use App\Enums\ReminderStatus;
use App\Models\Debt;
use App\Models\DebtPayment;
use App\Models\RecurringExpense;
use App\Models\Reminder;
use App\Models\SavingsGoal;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Recordatorios financieros del hogar (Épica 9, ADR-0027).
 *
 * Los avisos de gastos recurrentes, deudas y metas se DERIVAN en vivo de
 * su fuente (next_date, due_day, target_date): la fuente es la verdad y
 * ningún cron puede dejarlos caducados. Solo los recordatorios sueltos
 * del usuario ("obligación anual": tecnomecánica, pasaporte…) viven en
 * la tabla `reminders`.
 *
 * Seam de canales (ADR-0015): la "notificación" de hoy es la campanita y
 * la página /recordatorios, que muestran este estado real. Un canal
 * futuro (WhatsApp/push) consumiría list()/summary() tal cual, sin tocar
 * la lógica — el servicio no conoce rutas ni HTTP (ADR-0010).
 */
class ReminderService
{
    /** Días de antelación con que un vencimiento pasa a "próximo". */
    public const UPCOMING_DAYS = 7;

    /**
     * Minutos de vida del resumen cacheado. Es solo red de seguridad (p. ej.
     * el paso de medianoche): toda mutación que altera el conteo invalida la
     * clave al instante vía ReminderSummaryCacheObserver.
     */
    public const SUMMARY_TTL_MINUTES = 10;

    /** Clave del resumen por hogar (compartida con el observer). */
    public static function summaryCacheKey(int $householdId): string
    {
        return "reminders.summary.{$householdId}";
    }

    /**
     * Lista unificada de obligaciones del hogar (fuentes derivadas +
     * recordatorios sueltos pendientes), ordenadas por fecha: las
     * vencidas primero, que son las urgentes.
     *
     * Cada ítem es un array serializable:
     * source, id, title, amount (?float), due_date, days_remaining,
     * status (ReminderStatus) y detail (línea secundaria opcional).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function list(int $householdId, ?CarbonInterface $reference = null): Collection
    {
        $today = $this->today($reference);

        return $this->fromRecurring($householdId, $today)
            ->merge($this->fromDebts($householdId, $today))
            ->merge($this->fromGoals($householdId, $today))
            ->merge($this->fromCustoms($householdId, $today))
            ->sortBy(fn (array $item) => $item['due_date']->getTimestamp())
            ->values();
    }

    /**
     * Conteo para la campanita y el panel: cuántas obligaciones piden
     * atención (vencidas + próximas). `total` incluye las de más
     * adelante, para la página completa.
     *
     * @return array{overdue: int, upcoming: int, attention: int, total: int}
     */
    public function summary(int $householdId, ?CarbonInterface $reference = null): array
    {
        $statuses = $this->list($householdId, $reference)
            ->map(fn (array $item) => $item['status']);

        $overdue = $statuses->filter(fn (ReminderStatus $s) => $s === ReminderStatus::Overdue)->count();
        $upcoming = $statuses->filter(fn (ReminderStatus $s) => $s === ReminderStatus::Upcoming)->count();

        return [
            'overdue' => $overdue,
            'upcoming' => $upcoming,
            'attention' => $overdue + $upcoming,
            'total' => $statuses->count(),
        ];
    }

    /**
     * Resumen cacheado para superficies que corren en cada petición (la
     * campanita del navbar y el banner del Panel): sin esto, cada página
     * autenticada pagaría las queries de list(). La invalidación real la
     * hacen los eventos de modelo (ReminderSummaryCacheObserver); el TTL
     * solo cubre el paso de medianoche.
     *
     * list() nunca se cachea: la página /recordatorios siempre muestra el
     * estado fresco.
     *
     * @return array{overdue: int, upcoming: int, attention: int, total: int}
     */
    public function cachedSummary(int $householdId): array
    {
        return Cache::remember(
            self::summaryCacheKey($householdId),
            now()->addMinutes(self::SUMMARY_TTL_MINUTES),
            fn () => $this->summary($householdId),
        );
    }

    /**
     * Atiende un recordatorio suelto. Si se repite (SOAT, tecnomecánica),
     * la fecha avanza una frecuencia y sigue pendiente; si es de una sola
     * vez, queda completado. Devuelve true si quedó completado.
     *
     * NO genera gasto ni mueve cuentas: un recordatorio es un aviso, no
     * un movimiento (igual que los aportes de metas, ADR-0025). Si el
     * usuario quiere el gasto, lo registra desde /gastos.
     */
    public function complete(Reminder $reminder): bool
    {
        if ($reminder->frequency !== null) {
            $reminder->forceFill([
                'due_date' => $reminder->frequency
                    ->advance($reminder->due_date)
                    ->toDateString(),
            ])->save();

            return false;
        }

        $reminder->forceFill(['status' => ReminderStatus::Completed->value])->save();

        return true;
    }

    // ---------------------------------------------------------------
    // Fuentes derivadas
    // ---------------------------------------------------------------

    /**
     * Gastos recurrentes activos: cada uno vence en su next_date (Épica 5).
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function fromRecurring(int $householdId, Carbon $today): Collection
    {
        return RecurringExpense::where('household_id', $householdId)
            ->active()
            ->orderBy('next_date')
            ->get()
            ->map(fn (RecurringExpense $recurring) => $this->item(
                ReminderSource::RecurringExpense,
                $recurring->id,
                $recurring->name,
                (float) $recurring->amount,
                $recurring->next_date,
                $today,
                $recurring->frequency->shortLabel($recurring->frequency_interval),
            ))
            // toBase(): sin esto el map devuelve Eloquent\Collection y el
            // merge() unificado exige modelos Eloquent (no arrays).
            ->toBase();
    }

    /**
     * Deudas vigentes con cuota: cada una vence en su día de pago del mes.
     * Si el mes en curso ya tiene un pago registrado, se avisa el siguiente
     * (pagar no debe seguir sonando la campanita).
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function fromDebts(int $householdId, Carbon $today): Collection
    {
        return Debt::where('household_id', $householdId)
            ->outstanding()
            ->whereNotNull('due_day')
            ->with('payments')
            ->get()
            ->map(fn (Debt $debt) => [
                'debt' => $debt,
                'due' => $this->nextDebtDueDate($debt, $today),
            ])
            // Sin cuota conocida (ni planeada ni mínima) no hay obligación
            // que avisar: el monto del aviso sería cero.
            ->filter(fn (array $entry) => $entry['due'] !== null
                && $entry['debt']->monthlyCommitment() > 0)
            ->map(fn (array $entry) => $this->item(
                ReminderSource::Debt,
                $entry['debt']->id,
                $entry['debt']->name,
                $entry['debt']->monthlyCommitment(),
                $entry['due'],
                $today,
                'Cuota mensual'.($entry['debt']->institution !== null ? ' · '.$entry['debt']->institution : ''),
            ))
            ->toBase();
    }

    /**
     * Metas vigentes con fecha objetivo: la obligación es aportar antes
     * de esa fecha; el monto pendiente es lo que falta para el objetivo.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function fromGoals(int $householdId, Carbon $today): Collection
    {
        return SavingsGoal::where('household_id', $householdId)
            ->outstanding()
            ->whereNotNull('target_date')
            ->orderBy('target_date')
            ->get()
            ->map(fn (SavingsGoal $goal) => $this->item(
                ReminderSource::SavingsGoal,
                $goal->id,
                $goal->name,
                $goal->remainingAmount(),
                $goal->target_date,
                $today,
                'Llevas '.$goal->progressPercent().'% del objetivo',
            ))
            ->toBase();
    }

    /**
     * Recordatorios sueltos pendientes del hogar (ADR-0027): los completados
     * dejan de avisar y quedan como historia en la página de recordatorios.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function fromCustoms(int $householdId, Carbon $today): Collection
    {
        return Reminder::where('household_id', $householdId)
            ->pending()
            ->orderBy('due_date')
            ->get()
            ->map(function (Reminder $reminder) use ($today): array {
                $item = $this->item(
                    ReminderSource::Custom,
                    $reminder->id,
                    $reminder->title,
                    $reminder->amount !== null ? (float) $reminder->amount : null,
                    $reminder->due_date,
                    $today,
                    $reminder->frequency?->shortLabel(),
                );

                // Extras que la vista necesita para editar el aviso suelto.
                return $item + [
                    'frequency_value' => $reminder->frequency?->value,
                    'notes' => $reminder->notes,
                ];
            })
            ->toBase();
    }

    /**
     * Próxima fecha de pago de una deuda: la de este mes si aún no llegó
     * (o vence hoy); si ya pasó, esa misma mientras no haya pago en el
     * mes (cuota vencida); con pago registrado, la del mes siguiente.
     */
    private function nextDebtDueDate(Debt $debt, Carbon $today): ?Carbon
    {
        $cursor = $today->copy()->startOfMonth();

        for ($i = 0; $i < 3; $i++) {
            // Un día 31 en un mes de 30 cae el último día del mes.
            $day = min((int) $debt->due_day, $cursor->daysInMonth);
            $due = $cursor->copy()->day($day)->startOfDay();

            if ($due->gte($today) || ! $this->monthHasPayment($debt, $due)) {
                return $due;
            }

            $cursor->addMonthNoOverflow();
        }

        return null;
    }

    /**
     * ¿Hay algún pago dentro del mes natural de esta fecha de vencimiento?
     * Sobre la colección eager-loaded (nada de una query por deuda).
     */
    private function monthHasPayment(Debt $debt, Carbon $due): bool
    {
        $from = $due->copy()->startOfMonth();
        $to = $due->copy()->endOfMonth();

        return $debt->payments->contains(fn (DebtPayment $payment) => Carbon::parse($payment->date)
            ->betweenIncluded($from, $to));
    }

    /**
     * Ítem común de la lista unificada.
     *
     * @return array<string, mixed>
     */
    private function item(
        ReminderSource $source,
        int $id,
        string $title,
        ?float $amount,
        CarbonInterface $dueDate,
        Carbon $today,
        ?string $detail = null,
    ): array {
        $due = Carbon::parse($dueDate)->startOfDay();

        return [
            'source' => $source,
            'id' => $id,
            'title' => $title,
            'amount' => $amount,
            'due_date' => $due,
            'days_remaining' => (int) round($today->diffInDays($due)),
            'status' => ReminderStatus::resolve($due, $today, self::UPCOMING_DAYS),
            'detail' => $detail,
        ];
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
