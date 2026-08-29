<?php

declare(strict_types=1);

namespace App\Services;

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
 * comprometer mis obligaciones?" (Épica 4, ADR-0014).
 *
 * Cuatro conceptos que NO se mezclan:
 *  - balance actual : saldo real hoy en las cuentas activas.
 *  - comprometido   : dinero del período ya reservado (presupuesto pendiente
 *                     + obligaciones futuras de épicas 5-7).
 *  - disponible     : ingresos esperados − gastado − comprometido.
 *                     Es el "puedes gastar".
 *  - libre          : balance actual − comprometido. Cuánto del dinero que
 *                     ya tienes no está reservado.
 *
 * Seam (ADR-0010): no depende de la capa HTTP. Recibe IDs y enums explícitos
 * y devuelve arrays serializables, válidos igual para Blade que para JSON.
 */
class BudgetCalculatorService
{
    public function __construct(
        private readonly RecurringExpenseService $recurringExpenses,
        private readonly DebtService $debts,
    ) {}

    /**
     * Resumen completo de un período. Es la única entrada que necesitan la
     * pantalla de presupuestos y la tarjeta del dashboard.
     *
     * @return array<string, mixed>
     */
    public function summary(
        int $householdId,
        BudgetScope $scope = BudgetScope::Month,
        ?CarbonInterface $reference = null,
    ): array {
        $today = $reference !== null
            ? Carbon::parse($reference)->startOfDay()
            : Carbon::now(config('app.timezone'))->startOfDay();

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

        // Comprometido por presupuesto = lo presupuestado que aún NO se ha
        // gastado. Se toma el mayor entre el total y la suma de categorías
        // para no contar dos veces cuando existen ambos (decisión de producto).
        $committedBudget = max(
            max(0.0, $totalBudget - $spent),
            (float) $categories->sum('remaining'),
        );

        // Componentes que llegan en épicas posteriores. Los de épicas no
        // iniciadas se declaran en cero (no se omiten) para que su épica solo
        // tenga que rellenar su término sin tocar la fórmula ni la UI (ADR-0014).
        // Épica 5: recurrentes activos con ocurrencia en la ventana, separados
        // en gastos fijos (alta frecuencia) y obligaciones (trimestral+).
        $recurringCommitted = $this->recurringExpenses->committedInRange($householdId, $from, $to);

        // Épica 6: cuotas de deuda que vencen en la ventana y aún no se han
        // pagado. Las ya pagadas salen del comprometido porque ese dinero ya
        // figura como gasto (ADR-0021): contarlas aquí lo restaría dos veces.
        $debtCommitted = $this->debts->committedInRange($householdId, $from, $to);

        $committed = [
            'budget' => $this->money($committedBudget),
            'fixed_expenses' => $this->money($recurringCommitted['fixed']),  // Épica 5 — arriendo, servicios…
            'recurring' => $this->money($recurringCommitted['recurring']),   // Épica 5 — SOAT, matrícula…
            'debt' => $this->money($debtCommitted),                          // Épica 6 — cuotas pendientes
            'savings' => 0.0,                                                 // Épica 7 — ahorro programado
        ];
        $committedTotal = array_sum($committed);
        $committed['total'] = $this->money($committedTotal);

        // --- Balance real y resultados ---
        $currentBalance = (float) Account::where('household_id', $householdId)
            ->where('is_active', true)
            ->sum('current_balance');

        $available = $expectedIncome - $spent - $committedTotal;
        $free = $currentBalance - $committedTotal;

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
            'current_balance' => $this->money($currentBalance),
            'available' => $this->money($available),
            'free' => $this->money($free),
            'daily_allowance' => $daysRemaining > 0 ? $this->money(max(0.0, $available) / $daysRemaining) : 0.0,

            'budget_defined' => $this->money($budgetDefined),
            'budget_total' => $this->money($totalBudget),
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
     * Suma de los ingresos mensuales esperados activos del hogar.
     */
    public function monthlyExpectedIncome(int $householdId): float
    {
        return (float) ExpectedIncome::where('household_id', $householdId)
            ->active()
            ->sum('amount');
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
     * Redondeo monetario a 2 decimales (ADR-0006).
     */
    private function money(float $value): float
    {
        return round($value, 2);
    }
}
