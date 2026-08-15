<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\MovementSummaryService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private readonly MovementSummaryService $summary) {}

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

        $byCategory = $this->summary->expensesByCategory($householdId, $from, $to);
        $trend = $this->summary->monthlyTrend($householdId, 6);
        $recent = $this->summary->recentMovements($householdId, 6);

        // Saldo total = suma de saldos actuales de las cuentas activas.
        $totalBalance = (float) $household->accounts()->sum('current_balance');

        // Datos serializables para Chart.js (se inyectan como JSON en la vista).
        $chartData = [
            'expensesByCategory' => [
                'labels' => $byCategory->pluck('name')->all(),
                'amounts' => $byCategory->pluck('total')->all(),
                'colors' => $byCategory->map(fn ($c) => $c['color'] ?? '#0f766e')->all(),
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
            'totals' => $totals,
            'totalBalance' => $totalBalance,
            'byCategory' => $byCategory,
            'recent' => $recent,
            'chartData' => $chartData,
        ]);
    }
}
