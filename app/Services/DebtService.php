<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\DebtPaymentType;
use App\Enums\DebtStatus;
use App\Enums\DebtStrategy;
use App\Models\Debt;
use App\Models\DebtPayment;
use App\Models\DebtRefinancing;
use App\Models\Household;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Lógica de deudas y tarjetas (Épica 6): saldos, pagos, refinanciación,
 * proyección de fin de deuda y el término que alimenta el dinero disponible
 * (ADR-0014).
 *
 * Seam (ADR-0010): no depende de la capa HTTP. Recibe IDs, modelos y fechas
 * explícitos y devuelve arrays/colecciones serializables, así que la futura
 * API (Épica 14) lo reutiliza tal cual.
 */
class DebtService
{
    public function __construct(
        private readonly MovementService $movements,
        private readonly DebtCalculator $calculator,
    ) {}

    // ---------------------------------------------------------------
    // Saldo
    // ---------------------------------------------------------------

    /**
     * Línea base del saldo (ADR-0020): el importe original de la deuda o,
     * si se refinanció, el saldo refinanciado más reciente.
     *
     * @return array{amount: float, since: ?Carbon}
     */
    public function baseline(Debt $debt): array
    {
        $refinancing = $debt->relationLoaded('latestRefinancing')
            ? $debt->latestRefinancing
            : $debt->refinancings()->orderByDesc('start_date')->orderByDesc('id')->first();

        if ($refinancing === null) {
            return ['amount' => (float) $debt->original_amount, 'since' => null];
        }

        return [
            'amount' => (float) $refinancing->refinanced_balance,
            'since' => Carbon::parse($refinancing->start_date)->startOfDay(),
        ];
    }

    /**
     * Recalcula y persiste el saldo: línea base − pagos posteriores a ella
     * (ADR-0020). Nunca baja de cero: una deuda sobrepagada queda en 0, no
     * en negativo (eso sería un préstamo a favor, que no es esta entidad).
     *
     * Al llegar a cero la deuda se marca como pagada; si vuelve a haber
     * saldo (se borró un pago) regresa a activa.
     */
    public function recalculateBalance(Debt $debt): Debt
    {
        $baseline = $this->baseline($debt);

        $paid = (float) $debt->payments()
            ->when(
                $baseline['since'] !== null,
                fn ($query) => $query->whereDate('date', '>=', $baseline['since']->toDateString()),
            )
            ->sum('amount');

        $balance = round(max(0.0, $baseline['amount'] - $paid), 2);

        $attributes = ['current_balance' => $balance];

        // El estado solo se toca entre "activa" y "pagada": una deuda
        // condonada o refinanciada mantiene su estado, que es información
        // que el usuario puso a mano.
        if ($balance <= 0.0 && $debt->status === DebtStatus::Active) {
            $attributes['status'] = DebtStatus::Paid;
        } elseif ($balance > 0.0 && $debt->status === DebtStatus::Paid) {
            $attributes['status'] = DebtStatus::Active;
        }

        $debt->forceFill($attributes)->save();

        return $debt;
    }

    // ---------------------------------------------------------------
    // Altas y pagos
    // ---------------------------------------------------------------

    /**
     * Crea una deuda. El saldo arranca en la línea base, no lo teclea el
     * usuario (ADR-0020).
     *
     * @param  array<string, mixed>  $data
     */
    public function createDebt(Household $household, array $data): Debt
    {
        $debt = $household->debts()->make($data);
        $debt->current_balance = $data['original_amount'];
        $debt->minimum_payment = $this->deriveInstallment($debt);
        $debt->end_date = $this->deriveEndDate($debt);
        $debt->save();

        return $debt;
    }

    /**
     * Actualiza los datos editables y recalcula lo derivado: la fecha de fin
     * (inicio + cuotas, ADR-0022) y el saldo (ADR-0020).
     *
     * @param  array<string, mixed>  $data
     */
    public function updateDebt(Debt $debt, array $data): Debt
    {
        $debt->fill($data);
        $debt->minimum_payment = $this->deriveInstallment($debt);
        $debt->end_date = $this->deriveEndDate($debt);
        $debt->save();

        return $this->recalculateBalance($debt);
    }

    /**
     * Cuota mensual exigida (ADR-0023). Si el usuario no la escribió —porque
     * la dejó calculada, o porque tiene el JavaScript desactivado— sale del
     * monto, la tasa y el plazo. Si la escribió, se respeta: su entidad puede
     * cobrarle seguros o cuota de manejo encima.
     */
    private function deriveInstallment(Debt $debt): ?float
    {
        if ($debt->minimum_payment !== null) {
            return (float) $debt->minimum_payment;
        }

        return $this->calculator->installment(
            $debt->original_amount !== null ? (float) $debt->original_amount : null,
            $debt->interest_rate !== null ? (float) $debt->interest_rate : null,
            $debt->term_months,
        );
    }

    /**
     * Fecha de fin prevista: uno pacta un número de cuotas, no una fecha
     * (ADR-0022). Sin inicio o sin plazo no hay nada que derivar.
     */
    private function deriveEndDate(Debt $debt): ?string
    {
        if ($debt->start_date === null || $debt->term_months === null) {
            return null;
        }

        return Carbon::parse($debt->start_date)
            ->addMonthsNoOverflow((int) $debt->term_months)
            ->toDateString();
    }

    /**
     * Registra un pago contra la deuda y recalcula el saldo.
     *
     * Si se indica una cuenta del hogar, además crea el gasto real que mueve
     * su saldo (ADR-0021), todo dentro de una misma transacción: o quedan
     * las dos cosas o ninguna.
     *
     * @param  array<string, mixed>  $data  amount, date, type, notes, account_id?, category_id?
     */
    public function registerPayment(Debt $debt, array $data, User $user): DebtPayment
    {
        return DB::transaction(function () use ($debt, $data, $user): DebtPayment {
            $expense = null;
            $accountId = $data['account_id'] ?? null;

            if ($accountId !== null) {
                $expense = $this->movements->createExpense([
                    'account_id' => $accountId,
                    'category_id' => $data['category_id'] ?? null,
                    'amount' => $data['amount'],
                    'date' => $data['date'],
                    'description' => $debt->name.' (pago de deuda)',
                    'notes' => $data['notes'] ?? null,
                ], $debt->household, $user);
            }

            $payment = $debt->payments()->make([
                'amount' => $data['amount'],
                'date' => $data['date'],
                'type' => $data['type'] ?? DebtPaymentType::Scheduled->value,
                'notes' => $data['notes'] ?? null,
            ]);

            $payment->household_id = $debt->household_id;
            $payment->expense_id = $expense?->id;
            $payment->save();

            $this->recalculateBalance($debt);

            return $payment;
        });
    }

    /**
     * Borra un pago y deshace su efecto: elimina el gasto asociado (lo que
     * devuelve el dinero al saldo de la cuenta) y recalcula la deuda.
     */
    public function deletePayment(DebtPayment $payment): void
    {
        DB::transaction(function () use ($payment): void {
            $debt = $payment->debt;
            $expense = $payment->expense;

            $payment->delete();

            if ($expense !== null) {
                $this->movements->deleteExpense($expense);
            }

            $this->recalculateBalance($debt);
        });
    }

    /**
     * Registra una refinanciación: fija una nueva línea base y actualiza las
     * condiciones de la deuda (tasa, cuota, fin previsto).
     *
     * @param  array<string, mixed>  $data
     */
    public function registerRefinancing(Debt $debt, array $data): DebtRefinancing
    {
        return DB::transaction(function () use ($debt, $data): DebtRefinancing {
            $refinancing = $debt->refinancings()->make($data);
            $refinancing->household_id = $debt->household_id;
            $refinancing->save();

            $changes = ['status' => DebtStatus::Refinanced];

            if (! empty($data['interest_rate'])) {
                $changes['interest_rate'] = $data['interest_rate'];
            }

            if (! empty($data['installment'])) {
                $changes['planned_payment'] = $data['installment'];
            }

            if (! empty($data['term_months'])) {
                $changes['term_months'] = (int) $data['term_months'];
                // addMonthsNoOverflow: un 31 de enero + 1 mes es 28/29 de
                // febrero, no el 2 o 3 de marzo.
                $changes['end_date'] = Carbon::parse($data['start_date'])
                    ->addMonthsNoOverflow((int) $data['term_months'])
                    ->toDateString();
            }

            $debt->forceFill($changes)->save();

            // La relación cacheada quedaría obsoleta y el saldo saldría mal.
            $debt->unsetRelation('latestRefinancing');

            $this->recalculateBalance($debt);

            return $refinancing;
        });
    }

    // ---------------------------------------------------------------
    // Panel de deuda
    // ---------------------------------------------------------------

    /**
     * Resumen del hogar: deuda total, compromiso mensual y progreso.
     *
     * @return array<string, mixed>
     */
    public function summary(int $householdId): array
    {
        /** @var Collection<int, Debt> $debts */
        $debts = Debt::where('household_id', $householdId)->outstanding()->get();

        $totalBalance = round((float) $debts->sum(fn (Debt $d) => (float) $d->current_balance), 2);
        $totalOriginal = round((float) $debts->sum(fn (Debt $d) => (float) $d->original_amount), 2);
        $monthlyCommitment = round((float) $debts->sum(fn (Debt $d) => $d->monthlyCommitment()), 2);

        return [
            'total_balance' => $totalBalance,
            'total_original' => $totalOriginal,
            'total_paid' => round(max(0.0, $totalOriginal - $totalBalance), 2),
            'monthly_commitment' => $monthlyCommitment,
            'progress_percent' => $totalOriginal > 0.0
                ? round(max(0.0, min(100.0, ($totalOriginal - $totalBalance) / $totalOriginal * 100)), 1)
                : 0.0,
            'count' => $debts->count(),
        ];
    }

    /**
     * Deudas del hogar ordenadas según la estrategia elegida.
     *
     * La épica pide preparar la arquitectura de avalancha/bola de nieve: esto
     * es el criterio de orden, no un plan de pagos. Reparte del excedente
     * entre cuotas queda fuera del alcance de esta épica.
     *
     * @return Collection<int, Debt>
     */
    public function orderByStrategy(int $householdId, DebtStrategy $strategy): Collection
    {
        $debts = Debt::where('household_id', $householdId)->outstanding()->get();

        return match ($strategy) {
            // Mayor tasa primero; sin tasa conocida va al final.
            DebtStrategy::Avalanche => $debts
                ->sortByDesc(fn (Debt $d) => (float) ($d->interest_rate ?? -1))
                ->values(),
            // Menor saldo primero.
            DebtStrategy::Snowball => $debts
                ->sortBy(fn (Debt $d) => (float) $d->current_balance)
                ->values(),
        };
    }

    /**
     * Proyección de fin de deuda: a este ritmo, ¿cuándo quedaría saldada?
     *
     * ES UNA ESTIMACIÓN, y la UI debe decirlo. Amortiza el saldo mes a mes
     * aplicando la cuota y los intereses de la tasa anual repartida en doce.
     * No contempla cuotas de manejo, seguros, mora ni compras nuevas sobre
     * una tarjeta.
     *
     * @return array{months: ?int, date: ?Carbon, total_interest: float, never_ends: bool}
     */
    public function projectPayoff(Debt $debt, ?CarbonInterface $reference = null): array
    {
        // Misma matemática que el simulador del formulario (ADR-0023): si
        // cada uno usara la suya, la cuota calculada y la fecha proyectada se
        // contradirían en pantalla.
        $result = $this->calculator->payOff(
            (float) $debt->current_balance,
            $debt->interest_rate !== null ? (float) $debt->interest_rate : null,
            $debt->monthlyCommitment(),
        );

        $start = $reference !== null ? Carbon::parse($reference) : Carbon::now(config('app.timezone'));

        return [
            'months' => $result['months'],
            'date' => $result['months'] !== null
                ? $start->copy()->startOfDay()->addMonthsNoOverflow($result['months'])
                : null,
            'total_interest' => $result['total_interest'],
            'never_ends' => $result['never_ends'],
        ];
    }

    // ---------------------------------------------------------------
    // Seam del dinero disponible (ADR-0014)
    // ---------------------------------------------------------------

    /**
     * Cuotas de deuda comprometidas dentro de la ventana [from, to].
     *
     * Solo cuenta las que siguen PENDIENTES: si el pago de este mes ya se
     * registró, su cuota sale del comprometido, porque ese dinero ya figura
     * como gasto (ADR-0021). Sin esta resta, pagar una deuda haría bajar el
     * "puedes gastar" dos veces.
     */
    public function committedInRange(int $householdId, CarbonInterface $from, CarbonInterface $to): float
    {
        $from = Carbon::parse($from)->startOfDay();
        $to = Carbon::parse($to)->startOfDay();

        $committed = 0.0;

        Debt::where('household_id', $householdId)
            ->outstanding()
            ->get()
            ->each(function (Debt $debt) use (&$committed, $from, $to): void {
                $installment = $debt->monthlyCommitment();

                if ($installment <= 0.0) {
                    return;
                }

                foreach ($this->dueDatesInRange($debt, $from, $to) as $dueDate) {
                    if ($this->hasPaymentForMonth($debt, $dueDate)) {
                        continue;
                    }

                    // La última cuota nunca es mayor que lo que queda.
                    $committed += min($installment, (float) $debt->current_balance);
                }
            });

        return round($committed, 2);
    }

    // ---------------------------------------------------------------
    // Reportes (Épica 8)
    // ---------------------------------------------------------------

    /**
     * Saldo total de deuda a fin de cada uno de los últimos N meses.
     *
     * Es la serie del gráfico "Evolución de deuda": qué debía el hogar en
     * cada cierre de mes. Incluye deudas ya pagadas (su historia cuenta) y
     * excluye las borradas (SoftDeletes las saca del query).
     *
     * @return list<array{label: string, balance: float}>
     */
    public function balanceEvolution(int $householdId, int $months = 6): array
    {
        // Cargar payments y refinancings en memoria: a escala de hogar son
        // pocas filas y evita una query por deuda y mes.
        $debts = Debt::where('household_id', $householdId)
            ->with(['payments', 'refinancings'])
            ->get();

        $cursor = Carbon::now(config('app.timezone'))->startOfMonth();
        $points = [];

        for ($i = 0; $i < $months; $i++) {
            $cutoff = $cursor->copy()->endOfMonth()->endOfDay();

            $balance = $debts->sum(fn (Debt $debt) => $this->balanceAt($debt, $cutoff));

            array_unshift($points, [
                'label' => $cursor->locale('es')->isoFormat('MMM YY'),
                'balance' => round((float) $balance, 2),
            ]);

            $cursor->subMonth();
        }

        return $points;
    }

    /**
     * Saldo de una deuda en un corte temporal: ADR-0020 llevado al pasado.
     *
     * Línea base vigente EN ESE CORTE (original o refinanciación más
     * reciente con fecha ≤ corte) menos los pagos posteriores a ella, sin
     * bajar de cero. Antes de `start_date` la deuda no existía: suma 0.
     */
    public function balanceAt(Debt $debt, CarbonInterface $cutoff): float
    {
        $cutoff = Carbon::parse($cutoff);
        $start = $debt->start_date?->startOfDay();

        if ($start !== null && $start->gt($cutoff)) {
            return 0.0;
        }

        $base = (float) $debt->original_amount;
        $since = $start;

        // Refinanciaciones en orden ascendente: la última con fecha ≤ corte
        // es la línea base vigente en ese momento.
        foreach ($debt->refinancings->sortBy('start_date') as $refinancing) {
            $refStart = Carbon::parse($refinancing->start_date)->startOfDay();

            if ($refStart->lte($cutoff)) {
                $base = (float) $refinancing->refinanced_balance;
                $since = $refStart;
            }
        }

        $paid = (float) $debt->payments
            ->filter(function (DebtPayment $payment) use ($cutoff, $since): bool {
                $date = Carbon::parse($payment->date)->startOfDay();

                return $date->lte($cutoff)
                    && ($since === null || $date->gte($since));
            })
            ->sum('amount');

        return round(max(0.0, $base - $paid), 2);
    }

    /**
     * Fechas de pago de una deuda dentro de la ventana, según su día de pago
     * mensual. Sin `due_day` se asume una cuota por mes natural cubierto.
     *
     * @return array<int, Carbon>
     */
    private function dueDatesInRange(Debt $debt, Carbon $from, Carbon $to): array
    {
        $dates = [];
        $cursor = $from->copy()->startOfMonth();
        $guard = 0;

        while ($cursor->lte($to) && $guard++ < 120) {
            $day = $debt->due_day !== null
                // Un día 31 en un mes de 30 cae el último día del mes.
                ? min((int) $debt->due_day, $cursor->daysInMonth)
                : $cursor->daysInMonth;

            $dueDate = $cursor->copy()->day($day);

            if ($dueDate->betweenIncluded($from, $to)) {
                $dates[] = $dueDate;
            }

            $cursor->addMonthNoOverflow();
        }

        return $dates;
    }

    /**
     * ¿Ya hay un pago registrado para el mes de esta fecha de vencimiento?
     */
    private function hasPaymentForMonth(Debt $debt, Carbon $dueDate): bool
    {
        return $debt->payments()
            ->whereDate('date', '>=', $dueDate->copy()->startOfMonth()->toDateString())
            ->whereDate('date', '<=', $dueDate->copy()->endOfMonth()->toDateString())
            ->exists();
    }
}
