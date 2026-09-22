<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ReportFormat;
use App\Http\Requests\Report\ReportRequest;
use App\Services\DebtService;
use App\Services\MovementSummaryService;
use App\Services\ReportService;
use App\Services\SavingsGoalService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Reportes financieros del hogar (Épica 8): el dashboard completo con
 * comparación de períodos, los cinco gráficos, insights y exportación.
 *
 * Delgado por diseño (ADR-0010): valida (Form Request), resuelve el hogar
 * activo y delega en ReportService. Toda cifra que ve la vista sale del
 * Service, acotada al hogar.
 */
class ReportController extends Controller
{
    public function __construct(
        private readonly ReportService $reports,
        private readonly MovementSummaryService $movements,
        private readonly DebtService $debts,
        private readonly SavingsGoalService $savingsGoals,
    ) {}

    public function index(ReportRequest $request): View|RedirectResponse
    {
        $household = active_household();

        if ($household === null) {
            return redirect()->route('households.create');
        }

        $period = $request->period();
        $householdId = $household->id;

        $outstandingGoals = $this->savingsGoals->outstandingGoals($householdId);

        $overview = $this->reports->overview($householdId, $period, savingsGoals: $outstandingGoals);
        $insights = $this->reports->insights($householdId, $period);

        $byCategory = $this->movements->expensesByCategory($householdId, $overview['from'], $overview['to'], top: 5);
        $series = $this->reports->monthlySeries($householdId, $overview['from'], $overview['to']);
        $debtEvolution = $this->debts->balanceEvolution($householdId, months: 6);
        $goals = $outstandingGoals->take(6);

        $chartData = [
            'reportCategory' => MovementSummaryService::categoryChartData($byCategory),
            'reportTrend' => [
                'labels' => array_column($series, 'label'),
                'incomes' => array_column($series, 'incomes'),
                'expenses' => array_column($series, 'expenses'),
            ],
            'reportBalance' => [
                'labels' => array_column($series, 'label'),
                'balances' => array_column($series, 'balance'),
            ],
            'reportDebt' => [
                'labels' => array_column($debtEvolution, 'label'),
                'balances' => array_column($debtEvolution, 'balance'),
            ],
            'reportGoals' => [
                'labels' => $goals->pluck('name')->all(),
                'saved' => $goals->map(fn ($g) => (float) $g->current_amount)->all(),
                'remaining' => $goals->map(fn ($g) => $g->remainingAmount())->all(),
            ],
        ];

        return view('reports.index', [
            'household' => $household,
            'period' => $period,
            'overview' => $overview,
            'insights' => $insights,
            'byCategory' => $byCategory,
            'series' => $series,
            'debtEvolution' => $debtEvolution,
            'goals' => $goals,
            'chartData' => $chartData,
        ]);
    }

    /**
     * Exportación de los movimientos del período (CSV hoy; PDF es un caso
     * nuevo de ReportFormat, no un rediseño).
     */
    public function export(ReportRequest $request): StreamedResponse|RedirectResponse
    {
        $household = active_household();

        if ($household === null) {
            return redirect()->route('households.create');
        }

        $period = $request->period();
        $format = $request->exportFormat();

        $window = $period->resolve(Carbon::now(config('app.timezone')));

        $rows = $this->reports->exportRows($household->id, $window['from'], $window['to']);

        $filename = 'finlia-movimientos-'
            .$window['from']->format('Ymd').'-'.$window['to']->format('Ymd').'.csv';

        return match ($format) {
            ReportFormat::Csv => $this->csvResponse($rows, $filename),
        };
    }

    /**
     * Respuesta CSV en streaming: BOM UTF-8 y separador ';' para que Excel
     * en español (convención es-CO) lo abra bien sin importar nada.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     */
    private function csvResponse(Collection $rows, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');

            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, [
                'Fecha', 'Tipo', 'Categoría', 'Cuenta', 'Descripción', 'Registrado por', 'Monto',
            ], ';');

            foreach ($rows as $row) {
                fputcsv($out, [
                    $row['date']->format('d/m/Y'),
                    $row['type'] === 'income' ? 'Ingreso' : 'Gasto',
                    $this->csvText($row['category_name']),
                    $this->csvText($row['account_name'] ?? ''),
                    $this->csvText($row['description'] ?? ''),
                    $this->csvText($row['user_name'] ?? ''),
                    number_format((float) $row['amount'], 2, ',', ''),
                ], ';');
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Celda de texto del CSV controlada por el usuario (categoría, cuenta,
     * descripción, quien registra): se prefija ' a los valores que empiezan
     * por =, +, - o @ para que Excel no los evalúe como fórmula al abrir el
     * archivo (inyección CSV, OWASP). Fecha, tipo y monto los genera el
     * servidor y no necesitan escaparse.
     */
    private function csvText(?string $value): string
    {
        $value = (string) $value;

        if ($value === '' || ! in_array($value[0], ['=', '+', '-', '@'], true)) {
            return $value;
        }

        return "'{$value}";
    }
}
