<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\BudgetScope;
use App\Services\BudgetCalculatorService;
use App\Services\DebtService;
use App\Services\MovementSummaryService;
use App\Services\RecurringExpenseService;
use App\Services\ReminderService;
use App\Services\SavingsGoalService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private readonly MovementSummaryService $summary,
        private readonly BudgetCalculatorService $budgets,
        private readonly RecurringExpenseService $recurring,
        private readonly SavingsGoalService $savingsGoals,
        private readonly DebtService $debts,
        private readonly ReminderService $reminders,
    ) {}

    /**
     * Dashboard del mes con KPIs reales y datos para Chart.js.
     */
    public function __invoke(Request $request): View|RedirectResponse
    {
        $household = active_household();

        // Defensivo: un usuario autenticado siempre tiene hogar (ADR-0011).
        if ($household === null) {
            return redirect()->route('households.create');
        }

        $hoy = Carbon::now(config('app.timezone'))->locale('es');
        $householdId = $household->id;

        $totals = $this->summary->monthTotals($householdId, $hoy->year, $hoy->month);

        [$from, $to] = [$hoy->copy()->startOfMonth(), $hoy->copy()->endOfMonth()];

        // Top 5 + "Otras": una torta con muchas porciones no se lee en móvil.
        $byCategory = $this->summary->expensesByCategory($householdId, $from, $to, top: 5);
        $trend = $this->summary->monthlyTrend($householdId, 6);
        $recent = $this->summary->recentMovements($householdId, 6);

        // Saldo total = suma de saldos actuales de las cuentas activas.
        $totalBalance = (float) $household->accounts()->sum('current_balance');

        // Datos serializables para Chart.js (se inyectan como JSON en la vista).
        $chartData = [
            'expensesByCategory' => [
                'labels' => $byCategory->pluck('name')->all(),
                'amounts' => $byCategory->pluck('total')->all(),
                'colors' => $byCategory->map(fn ($c) => $c['color'] ?? '#0b3f44')->all(),
            ],
            'trend' => [
                'labels' => array_column($trend, 'label'),
                'incomes' => array_column($trend, 'incomes'),
                'expenses' => array_column($trend, 'expenses'),
            ],
        ];

        return view('dashboard', [
            'user' => $request->user(),
            'household' => $household,
            'fechaActual' => $hoy->isoFormat('dddd, D [de] MMMM [de] YYYY'),
            // Épica 4: "¿cuánto puedo gastar?" del mes en curso.
            'budgetSummary' => $this->budgets->summary($householdId, BudgetScope::Month),
            // Épica 5: avisos de obligaciones vencidas o próximas a vencer.
            'recurringAlerts' => $this->recurring->alerts($householdId),
            // Épica 7: progreso de las metas de ahorro vigentes.
            'savingsGoals' => $this->savingsGoals->outstandingGoals($householdId),
            // Épica 8: deuda total y ahorro acumulado completan el resumen.
            'debtSummary' => $this->debts->summary($householdId),
            'savingsSummary' => $this->savingsGoals->summary($householdId),
            // Épica 9: campanita del panel (null si el hogar los desactivó).
            'reminderSummary' => $household->reminders_enabled
                ? $this->reminders->cachedSummary($householdId)
                : null,
            'totals' => $totals,
            'totalBalance' => $totalBalance,
            'byCategory' => $byCategory,
            'recent' => $recent,
            'chartData' => $chartData,
        ]);
    }
}
