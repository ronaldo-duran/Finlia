<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AccountType;
use App\Enums\BudgetAlertLevel;
use App\Enums\BudgetPeriod;
use App\Enums\BudgetScope;
use App\Models\Account;
use App\Models\Budget;
use App\Models\ExpectedIncome;
use App\Models\Expense;
use App\Models\Income;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Responde la pregunta central de Finlia: "¿cuánto puedo gastar sin
 * comprometer mis obligaciones?" (Épica 4, ADR-0014, ADR-0040).
 *
 * Dos respuestas que NO se mezclan:
 *  - liquidez (hoy): el "puedes gastar hoy". Sale del saldo REAL de las
 *                    cuentas, menos lo que vence antes del próximo cobro,
 *                    repartido en los días que faltan para ese cobro. Lo que
 *                    aún no te han pagado nunca suma (ADR-0040).
 *  - plan (período): ingresos esperados − gastado − comprometido. Sirve para
 *                    planear (el próximo mes) y como tope de la liquidez,
 *                    nunca para aumentarla.
 *
 * Seam (ADR-0010): no depende de la capa HTTP. Recibe IDs y enums explícitos
 * y devuelve arrays serializables, válidos igual para Blade que para JSON.
 */
class BudgetCalculatorService
{
    /**
     * Un ingreso registrado hasta esta cantidad de días antes de la fecha de
     * cobro cuenta como ese pago: en Colombia, si el 15 cae en domingo o
     * festivo, el salario suele llegar el viernes anterior.
     */
    public const EARLY_PAYMENT_DAYS = 7;

    /**
     * Parte del ingreso esperado que tiene que haberse registrado para darlo
     * por recibido. Equivocarse aquí hacia el "sí" solo alarga el horizonte
     * (la cifra baja); hacia el "no" podría inflarla, por eso es generosa.
     */
    private const RECEIVED_SHARE = 0.5;

    public function __construct(
        private readonly RecurringExpenseService $recurringExpenses,
        private readonly DebtService $debts,
        private readonly SavingsGoalService $savingsGoals,
    ) {}

    /**
     * Resumen completo de un período: el plan del período y, salvo en
     * "próximo mes", la liquidez de hoy. Es la única entrada que necesitan la
     * pantalla de presupuestos y la tarjeta del dashboard.
     *
     * @return array<string, mixed>
     */
    public function summary(
        int $householdId,
        BudgetScope $scope = BudgetScope::Month,
        ?CarbonInterface $reference = null,
    ): array {
        $today = $this->today($reference);
        $obligations = $this->obligations($householdId);
        $summary = $this->plan($householdId, $scope, $today, $obligations);

        // La liquidez siempre es de hoy. En "próximo mes" no aplica: nadie
        // gasta hoy la plata de un mes que aún no empieza.
        $summary['liquidity'] = match ($scope) {
            BudgetScope::NextMonth => null,
            BudgetScope::Month => $this->computeLiquidity($householdId, $today, $summary, $obligations),
            BudgetScope::Week => $this->computeLiquidity(
                $householdId,
                $today,
                $this->plan($householdId, BudgetScope::Month, $today, $obligations),
                $obligations,
            ),
        };

        return $summary;
    }

    /**
     * "Puedes gastar hoy" (ADR-0040).
     *
     *   disponible = saldo real − apartado en metas − lo que vence antes del cobro
     *   hoy        = disponible ÷ días hasta el cobro
     *
     * El plan del mes actúa solo como TOPE: si tienes mucho más en cuentas de
     * lo que tu mes permite (ahorros que no están en una meta, un salario que
     * llegó antes), la cifra no se dispara. Nunca la aumenta.
     *
     * @return array<string, mixed>
     */
    public function liquidity(int $householdId, ?CarbonInterface $reference = null): array
    {
        $today = $this->today($reference);
        $obligations = $this->obligations($householdId);

        return $this->computeLiquidity(
            $householdId,
            $today,
            $this->plan($householdId, BudgetScope::Month, $today, $obligations),
            $obligations,
        );
    }

    /**
     * @param  array<string, mixed>  $monthPlan  plan del mes en curso (el tope)
     * @param  array{recurring: Collection, debts: Collection, goals: Collection}  $obligations
     * @return array<string, mixed>
     */
    private function computeLiquidity(int $householdId, Carbon $today, array $monthPlan, array $obligations): array
    {
        $payday = $this->payday($householdId, $today);
        $until = $payday['date']->copy()->subDay();   // último día que cubre el saldo de hoy
        $days = (int) $today->diffInDays($payday['date']);

        // --- Saldo real ---
        // Una tarjeta de crédito solo resta: su saldo positivo es cupo, es
        // decir, plata prestada, no plata tuya.
        $accounts = Account::where('household_id', $householdId)
            ->where('is_active', true)
            ->get(['type', 'current_balance']);
        $balance = (float) $accounts->sum(fn (Account $a) => $a->type === AccountType::CreditCard
            ? min(0.0, (float) $a->current_balance)
            : (float) $a->current_balance);

        // --- Lo que ya tiene dueño antes del cobro ---
        $setAside = $this->savingsGoals->setAside($householdId, $obligations['goals']);
        $recurring = $this->recurringExpenses->dueUntil($householdId, $today, $until, $obligations['recurring']);
        $debt = $this->debts->dueUntil($householdId, $today, $until, $obligations['debts']);

        // El ahorro programado no tiene fecha: se reparte a lo largo del ciclo
        // de cobro y aquí se aparta la parte de los días que faltan.
        $cycleDays = max(1, (int) $payday['cycle_start']->diffInDays($payday['date']));
        $savings = $this->savingsGoals->pendingCommitmentSince($householdId, $payday['cycle_start'], $obligations['goals'])
            * min(1.0, $days / $cycleDays);

        $reserved = [
            'fixed_expenses' => $this->money($recurring['fixed']),   // arriendo, servicios…
            'recurring' => $this->money($recurring['recurring']),    // SOAT, matrícula…
            'debt' => $this->money($debt),                            // cuotas pendientes
            'savings' => $this->money($savings),                      // ahorro programado
        ];
        $reservedTotal = array_sum($reserved);
        $reserved['total'] = $this->money($reservedTotal);

        $cash = $balance - $setAside - $reservedTotal;

        // --- Tope del plan del mes ---
        $planLimit = null;
        if ($monthPlan['has_expected_income'] && $monthPlan['days_remaining'] > 0) {
            $planLimit = $monthPlan['plan_available'] / $monthPlan['days_remaining'] * $days;
        }
        $limitedByPlan = $planLimit !== null && $planLimit < $cash;
        $available = $limitedByPlan ? $planLimit : $cash;

        // Si no alcanza, lo urgente es el saldo: faltar plata antes del cobro
        // pesa más que haberse pasado del plan del mes.
        [$status, $shortfall] = match (true) {
            $cash < 0 => ['short', -$cash],
            $planLimit !== null && $planLimit < 0 => ['over_plan', -$monthPlan['plan_available']],
            default => ['ok', 0.0],
        };

        return [
            'status' => $status,
            'shortfall' => $this->money($shortfall),
            'today' => $today,
            'payday' => $payday['date'],
            'until' => $until,
            'days' => $days,
            'payday_known' => $payday['known'],
            'payday_income' => $payday['name'],
            'pending_incomes' => $payday['pending'],
            'has_expected_income' => $monthPlan['has_expected_income'],
            'has_accounts' => $accounts->isNotEmpty(),

            'current_balance' => $this->money($balance),
            'set_aside' => $this->money($setAside),
            'reserved' => $reserved,
            'cash_available' => $this->money($cash),
            'plan_limit' => $planLimit !== null ? $this->money($planLimit) : null,
            'plan_available' => $monthPlan['plan_available'],   // plan del mes en curso
            'limited_by' => $limitedByPlan ? 'plan' : 'cash',
            'available' => $this->money($available),
            'daily_allowance' => $days > 0 ? $this->money(max(0.0, $available) / $days) : 0.0,
        ];
    }

    /**
     * Plan de un período: lo que esperas recibir contra lo gastado y lo
     * comprometido. Es una proyección, no plata disponible.
     *
     * @param  array{recurring: Collection, debts: Collection, goals: Collection}  $obligations
     * @return array<string, mixed>
     */
    private function plan(int $householdId, BudgetScope $scope, Carbon $today, array $obligations): array
    {
        $window = $this->resolveWindow($scope, $today);
        ['from' => $from, 'to' => $to, 'factor' => $factor] = $window;

        // --- Días del período (para "días restantes" y el ritmo de gasto) ---
        $daysTotal = $window['days_total'];
        $daysElapsed = match (true) {
            $today->lt($from) => 0,                                              // período futuro
            $today->gt($to) => $daysTotal,                                       // período pasado
            default => (int) $from->copy()->diffInDays($today) + 1,
        };
        // Incluye hoy: en el último día del mes queda 1 día para gastar.
        $daysRemaining = match (true) {
            $today->gt($to) => 0,
            $today->lt($from) => $daysTotal,
            default => $daysTotal - $daysElapsed + 1,
        };

        // --- Ingresos esperados ---
        $expectedMonthly = $this->monthlyExpectedIncome($householdId);
        $registeredIncome = (float) Income::where('household_id', $householdId)
            ->whereBetween('date', [$from, $to])
            ->sum('amount');
        // Se toma el mayor para no contar dos veces el mismo salario cuando ya
        // se registró, y para no quedarse corto si entró más de lo previsto.
        $expectedIncome = max($expectedMonthly * $factor, $registeredIncome);

        // --- Gasto real del período ---
        $spent = (float) Expense::where('household_id', $householdId)
            ->whereBetween('date', [$from, $to])
            ->sum('amount');

        // --- Presupuestos del mes de referencia ---
        $budgets = Budget::where('household_id', $householdId)
            ->where('period', BudgetPeriod::Monthly->value)
            ->forMonth($window['year'], $window['month'])
            ->with('category')
            ->get();

        $totalBudgetModel = $budgets->first(fn (Budget $b) => $b->category_id === null);
        $totalBudget = $totalBudgetModel !== null ? (float) $totalBudgetModel->amount * $factor : 0.0;
        $categoryBudgets = $budgets->filter(fn (Budget $b) => $b->category_id !== null)->values();

        $categories = $this->categoryBreakdown($householdId, $categoryBudgets, $from, $to, $factor);

        $categoryBudgetSum = (float) $categories->sum('budget');
        $budgetDefined = max($totalBudget, $categoryBudgetSum);

        // Presupuesto aún sin gastar: el mayor entre el total y la suma de
        // categorías, para no contar dos veces cuando existen ambos. Es
        // informativo: el presupuesto reparte lo que puedes gastar, no lo
        // reduce (ADR-0040), así que no entra en el comprometido.
        $budgetRemaining = max(
            max(0.0, $totalBudget - $spent),
            (float) $categories->sum('remaining'),
        );

        // Obligaciones del período (épicas 5-7, ADR-0014): recurrentes con
        // ocurrencia en la ventana, cuotas de deuda aún sin pagar (las pagadas
        // ya figuran como gasto, ADR-0021) y aporte mensual de metas activas.
        $recurringCommitted = $this->recurringExpenses->committedInRange($householdId, $from, $to, $obligations['recurring']);
        $debtCommitted = $this->debts->committedInRange($householdId, $from, $to, $obligations['debts']);
        $savingsCommitted = $this->savingsGoals->committedMonthly($householdId, $obligations['goals']);

        $committed = [
            'fixed_expenses' => $this->money($recurringCommitted['fixed']),  // arriendo, servicios…
            'recurring' => $this->money($recurringCommitted['recurring']),   // SOAT, matrícula…
            'debt' => $this->money($debtCommitted),                          // cuotas pendientes
            'savings' => $this->money($savingsCommitted),                    // ahorro programado
        ];
        $committedTotal = array_sum($committed);
        $committed['total'] = $this->money($committedTotal);

        $planAvailable = $expectedIncome - $spent - $committedTotal;

        // --- Indicadores ---
        $consumedPercent = $budgetDefined > 0 ? round($spent / $budgetDefined * 100, 1) : null;
        $projectedSpend = $daysElapsed > 0 ? $spent / $daysElapsed * $daysTotal : 0.0;

        return [
            'scope' => $scope,
            'from' => $from,
            'to' => $to,
            'year' => $window['year'],
            'month' => $window['month'],
            'prorated' => $factor < 1.0,
            'days_total' => $daysTotal,
            'days_elapsed' => $daysElapsed,
            'days_remaining' => $daysRemaining,

            'expected_income' => $this->money($expectedIncome),
            'expected_income_monthly' => $this->money($expectedMonthly),
            'registered_income' => $this->money($registeredIncome),
            'spent' => $this->money($spent),
            'committed' => $committed,
            'plan_available' => $this->money($planAvailable),

            'budget_defined' => $this->money($budgetDefined),
            'budget_total' => $this->money($totalBudget),
            'budget_remaining' => $this->money($budgetRemaining),
            'has_budget' => $budgetDefined > 0,
            'has_expected_income' => $expectedMonthly > 0,
            'consumed_percent' => $consumedPercent,
            'level' => $consumedPercent !== null ? BudgetAlertLevel::fromPercent($consumedPercent) : null,
            'projected_spend' => $this->money($projectedSpend),
            'trend' => $this->trend($projectedSpend, $budgetDefined > 0 ? $budgetDefined : $expectedIncome),

            'categories' => $categories,
            'exceeded' => $categories->where('level', BudgetAlertLevel::Exceeded)->values(),
            'warnings' => $categories->where('level', BudgetAlertLevel::Warning)->values(),
        ];
    }

    /**
     * Recurrentes, deudas y metas del hogar, cargados una sola vez: el plan y
     * la liquidez leen los mismos (antes eran dos consultas de cada uno).
     *
     * @return array{recurring: Collection, debts: Collection, goals: Collection}
     */
    private function obligations(int $householdId): array
    {
        return [
            'recurring' => $this->recurringExpenses->activeItems($householdId),
            'debts' => $this->debts->outstandingWithPayments($householdId),
            'goals' => $this->savingsGoals->nonArchived($householdId),
        ];
    }

    /**
     * Suma de los ingresos mensuales esperados activos del hogar.
     */
    public function monthlyExpectedIncome(int $householdId): float
    {
        return (float) ExpectedIncome::where('household_id', $householdId)
            ->active()
            ->sum('amount');
    }

    /**
     * Hasta cuándo tiene que alcanzar el saldo de hoy (ADR-0040).
     *
     * El horizonte es el próximo pago del ingreso PRINCIPAL (el mayor; a
     * igual monto, el más cercano: dos quincenas iguales se turnan). Un
     * ingreso menor que llegue antes no lo acorta: si se atrasa, no deja al
     * hogar corto. Cuando llegue y se registre, el saldo sube y la cifra con él.
     *
     * Sin ingreso principal con día de cobro, el horizonte es el fin de mes.
     *
     * @return array{date: Carbon, cycle_start: Carbon, known: bool, name: string|null, pending: list<array<string, mixed>>}
     */
    private function payday(int $householdId, Carbon $today): array
    {
        $incomes = ExpectedIncome::where('household_id', $householdId)
            ->active()
            ->where('amount', '>', 0)
            ->get();

        // Ingresos recientes, para saber si un pago ya llegó (a tiempo o antes).
        $registered = Income::where('household_id', $householdId)
            ->whereBetween('date', [$today->copy()->subDays(45)->toDateString(), $today->toDateString()])
            ->get(['amount', 'date']);

        $received = function (Carbon $payday, float $amount) use ($registered, $today): bool {
            $from = $payday->copy()->subDays(self::EARLY_PAYMENT_DAYS);

            if ($from->gt($today)) {
                return false;
            }

            $sum = $registered
                ->filter(fn (Income $i) => Carbon::parse($i->date)->startOfDay()->betweenIncluded($from, $today))
                ->sum(fn (Income $i) => (float) $i->amount);

            return $sum >= $amount * self::RECEIVED_SHARE;
        };

        // Pagos que ya debían haber llegado y no aparecen. Solo desde que el
        // ingreso está configurado: el cobro de antes de empezar a usar
        // Finlia ya es parte del saldo inicial.
        $pending = [];
        foreach ($incomes->whereNotNull('day_of_month') as $income) {
            $last = $this->occurrenceOnOrBefore((int) $income->day_of_month, $today);

            if ($last->gte(Carbon::parse($income->created_at)->startOfDay())
                && ! $received($last, (float) $income->amount)) {
                $pending[] = [
                    'name' => $income->name,
                    'amount' => $this->money((float) $income->amount),
                    'date' => $last,
                    'is_today' => $last->eq($today),
                ];
            }
        }

        $max = (float) $incomes->max(fn (ExpectedIncome $i) => (float) $i->amount);
        $main = $incomes->filter(fn (ExpectedIncome $i) => (float) $i->amount >= $max);
        $dated = $main->whereNotNull('day_of_month');

        if ($dated->isEmpty()) {
            return [
                'date' => $today->copy()->addMonthNoOverflow()->startOfMonth(),
                'cycle_start' => $today->copy()->startOfMonth(),
                'known' => false,
                'name' => $main->first()?->name,
                'pending' => $pending,
            ];
        }

        $best = null;
        foreach ($dated as $income) {
            $day = (int) $income->day_of_month;
            $next = $this->occurrenceAfter($day, $today);

            // Pago adelantado: esa plata ya está en el saldo, así que tiene
            // que alcanzar hasta el cobro siguiente.
            if ($received($next, (float) $income->amount)) {
                $next = $this->occurrenceAfter($day, $next);
            }

            if ($best === null || $next->lt($best['date'])) {
                $best = ['date' => $next, 'day' => $day, 'name' => $income->name];
            }
        }

        $cycleStart = $this->occurrenceOnOrBefore($best['day'], $best['date']->copy()->subDay());

        return [
            'date' => $best['date'],
            'cycle_start' => $cycleStart->gt($today) ? $today->copy() : $cycleStart,
            'known' => true,
            'name' => $best['name'],
            'pending' => $pending,
        ];
    }

    /**
     * Primera fecha de cobro estrictamente posterior a `$date`. Un día 31 en
     * un mes de 30 cae el último día del mes.
     */
    private function occurrenceAfter(int $day, Carbon $date): Carbon
    {
        $candidate = $this->occurrenceIn($date, $day);

        return $candidate->gt($date)
            ? $candidate
            : $this->occurrenceIn($date->copy()->startOfMonth()->addMonthNoOverflow(), $day);
    }

    /**
     * Última fecha de cobro en o antes de `$date`.
     */
    private function occurrenceOnOrBefore(int $day, Carbon $date): Carbon
    {
        $candidate = $this->occurrenceIn($date, $day);

        return $candidate->lte($date)
            ? $candidate
            : $this->occurrenceIn($date->copy()->startOfMonth()->subMonthNoOverflow(), $day);
    }

    private function occurrenceIn(Carbon $month, int $day): Carbon
    {
        $start = $month->copy()->startOfMonth();

        return $start->day(min($day, $start->daysInMonth));
    }

    /**
     * Detalle por categoría presupuestada: gastado, restante, % y nivel de alerta.
     *
     * @param  Collection<int, Budget>  $categoryBudgets
     * @return Collection<int, array<string, mixed>>
     */
    private function categoryBreakdown(
        int $householdId,
        Collection $categoryBudgets,
        Carbon $from,
        Carbon $to,
        float $factor,
    ): Collection {
        if ($categoryBudgets->isEmpty()) {
            return collect();
        }

        // Una sola consulta agregada para todas las categorías (evita N+1).
        $spentByCategory = Expense::where('household_id', $householdId)
            ->whereBetween('date', [$from, $to])
            ->whereNotNull('category_id')
            ->groupBy('category_id')
            ->selectRaw('category_id, SUM(amount) as total')
            ->pluck('total', 'category_id');

        return $categoryBudgets
            ->map(function (Budget $budget) use ($spentByCategory, $factor): array {
                $amount = (float) $budget->amount * $factor;
                $spent = (float) ($spentByCategory[$budget->category_id] ?? 0);
                $percent = $amount > 0 ? $spent / $amount * 100 : 0.0;

                return [
                    'budget_id' => $budget->id,
                    'category_id' => $budget->category_id,
                    'name' => $budget->category?->name ?? 'Sin categoría',
                    'color' => $budget->category?->color,
                    'budget' => $this->money($amount),
                    'spent' => $this->money($spent),
                    'remaining' => $this->money(max(0.0, $amount - $spent)),
                    'overspent' => $this->money(max(0.0, $spent - $amount)),
                    'percent' => round($percent, 1),
                    'level' => BudgetAlertLevel::fromPercent($percent),
                ];
            })
            ->sortByDesc('percent')
            ->values();
    }

    /**
     * Ventana temporal consultada y factor de prorrateo del presupuesto mensual.
     *
     * La semana no tiene presupuesto propio: se prorratea el mensual del mes
     * de referencia (7 días de 31 ≈ 22,6 %).
     *
     * @return array{from: Carbon, to: Carbon, year: int, month: int, factor: float, days_total: int}
     */
    private function resolveWindow(BudgetScope $scope, Carbon $today): array
    {
        $anchor = match ($scope) {
            BudgetScope::NextMonth => $today->copy()->addMonthNoOverflow()->startOfMonth(),
            default => $today->copy(),
        };

        [$from, $to] = match ($scope) {
            BudgetScope::Week => [$today->copy()->startOfWeek(), $today->copy()->endOfWeek()],
            BudgetScope::Month => [$today->copy()->startOfMonth(), $today->copy()->endOfMonth()],
            BudgetScope::NextMonth => [$anchor->copy()->startOfMonth(), $anchor->copy()->endOfMonth()],
        };

        $from = $from->startOfDay();
        $daysTotal = (int) $from->diffInDays($to->copy()->startOfDay()) + 1;
        $daysInMonth = (int) $anchor->daysInMonth;

        return [
            'from' => $from,
            'to' => $to->endOfDay(),
            'year' => $anchor->year,
            'month' => $anchor->month,
            'factor' => $daysInMonth > 0 ? min(1.0, $daysTotal / $daysInMonth) : 1.0,
            'days_total' => $daysTotal,
        ];
    }

    /**
     * Ritmo de gasto proyectado frente a la referencia del período.
     * Margen del 5 % para no marcar desviaciones triviales.
     */
    private function trend(float $projected, float $reference): ?string
    {
        if ($reference <= 0) {
            return null;
        }

        return match (true) {
            $projected > $reference * 1.05 => 'over',
            $projected < $reference * 0.95 => 'under',
            default => 'on_track',
        };
    }

    /**
     * Hoy (o la fecha de referencia de los tests) al inicio del día, en la
     * zona de la aplicación (America/Bogota).
     */
    private function today(?CarbonInterface $reference): Carbon
    {
        return $reference !== null
            ? Carbon::parse($reference)->startOfDay()
            : Carbon::now(config('app.timezone'))->startOfDay();
    }

    /**
     * Redondeo monetario a 2 decimales (ADR-0006).
     */
    private function money(float $value): float
    {
        return round($value, 2);
    }
}
