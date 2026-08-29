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
    /** Techo de meses que se proyecta hacia adelante (50 años). */
    private const MAX_PROJECTION_MONTHS = 600;

    public function __construct(private readonly MovementService $movements) {}

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
        $debt->end_date = $this->deriveEndDate($debt);
        $debt->save();

        return $this->recalculateBalance($debt);
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
        $balance = (float) $debt->current_balance;
        $payment = $debt->monthlyCommitment();
        $monthlyRate = (float) ($debt->interest_rate ?? 0) / 100 / 12;
        $start = $reference !== null ? Carbon::parse($reference) : Carbon::now(config('app.timezone'));

        $empty = ['months' => null, 'date' => null, 'total_interest' => 0.0, 'never_ends' => false];

        if ($balance <= 0.0) {
            return $empty;
        }

        // Sin cuota registrada no hay ritmo que proyectar.
        if ($payment <= 0.0) {
            return [...$empty, 'never_ends' => true];
        }

        // Si la cuota no cubre ni los intereses del primer mes, el saldo
        // crece: la deuda no termina nunca a este ritmo. Decirlo es más útil
        // que devolver una fecha inventada.
        if ($payment <= $balance * $monthlyRate) {
            return [...$empty, 'never_ends' => true];
        }

        $totalInterest = 0.0;
        $months = 0;

        while ($balance > 0.0 && $months < self::MAX_PROJECTION_MONTHS) {
            $interest = round($balance * $monthlyRate, 2);
            $totalInterest += $interest;
            $balance = round($balance + $interest - $payment, 2);
            $months++;
        }

        if ($balance > 0.0) {
            return [...$empty, 'never_ends' => true];
        }

        return [
            'months' => $months,
            'date' => $start->copy()->startOfDay()->addMonths($months),
            'total_interest' => round($totalInterest, 2),
            'never_ends' => false,
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
