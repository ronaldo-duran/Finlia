<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ReportPeriod;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Reportes financieros del hogar (Épica 8): resumen comparado por período,
 * series mensuales para los gráficos, insights descriptivos y las filas de
 * la exportación.
 *
 * Seam (ADR-0010): no depende de la capa HTTP. Recibe el hogar y el
 * período como datos explícitos y devuelve arrays/colecciones
 * serializables, válidos igual para Blade que para la futura API.
 */
class ReportService
{
    /** Umbrales de los insights: por debajo, el cambio es ruido y no se afirma. */
    private const TOTAL_CHANGE_THRESHOLD = 0.05;

    private const CATEGORY_CHANGE_THRESHOLD = 0.15;

    private const TOP_CATEGORY_SHARE = 10.0;

    private const MAX_INSIGHTS = 4;

    public function __construct(
        private readonly MovementSummaryService $movements,
        private readonly DebtService $debts,
        private readonly SavingsGoalService $savingsGoals,
    ) {}

    /**
     * Resumen del período seleccionado, comparado contra el período anterior
     * equivalente (el mes anterior contra el anterior a aquel, etc.).
     *
     * Deuda y ahorro son punto en el tiempo: no dependen del período, pero
     * la épica los quiere en el mismo resumen.
     *
     * @return array<string, mixed>
     */
    public function overview(int $householdId, ReportPeriod $period, ?CarbonInterface $reference = null): array
    {
        $window = $period->resolve($reference ?? Carbon::now(config('app.timezone')));

        $current = $this->movements->rangeTotals($householdId, $window['from'], $window['to']);
        $previous = $this->movements->rangeTotals($householdId, $window['previous_from'], $window['previous_to']);

        return [
            'period' => $period,
            'from' => $window['from'],
            'to' => $window['to'],
            'label' => $this->rangeLabel($window['from'], $window['to']),
            'previous_label' => $this->rangeLabel($window['previous_from'], $window['previous_to']),
            'incomes' => $current['incomes'],
            'expenses' => $current['expenses'],
            'balance' => $current['balance'],
            'previous' => $previous,
            'deltas' => [
                'incomes' => $this->delta($current['incomes'], $previous['incomes']),
                'expenses' => $this->delta($current['expenses'], $previous['expenses']),
                'balance' => $this->delta($current['balance'], $previous['balance']),
            ],
            'debt' => $this->debts->summary($householdId),
            'savings' => $this->savingsGoals->summary($householdId),
        ];
    }

    /**
     * Serie mensual del rango: ingresos, gastos y balance por mes natural
     * cubierto. Es la fuente de los gráficos "Ingresos vs gastos" y
     * "Evolución mensual".
     *
     * @return list<array{label: string, incomes: float, expenses: float, balance: float}>
     */
    public function monthlySeries(int $householdId, CarbonInterface $from, CarbonInterface $to): array
    {
        $series = [];
        $cursor = Carbon::parse($from)->startOfMonth();
        $end = Carbon::parse($to);

        while ($cursor->lte($end) && count($series) < 12) {
            $totals = $this->movements->monthTotals($householdId, $cursor->year, $cursor->month);

            $series[] = [
                'label' => $cursor->locale('es')->isoFormat('MMM YY'),
                'incomes' => $totals['incomes'],
                'expenses' => $totals['expenses'],
                'balance' => round($totals['incomes'] - $totals['expenses'], 2),
            ];

            $cursor->addMonthNoOverflow();
        }

        return $series;
    }

    /**
     * Insights descriptivos del período (Épica 8).
     *
     * Reglas de la épica: solo hechos calculados contra datos que EXISTEN
     * (sin período anterior no hay porcentajes), nada de recomendaciones
     * financieras, y umbrales mínimos para no convertir ruido en aviso.
     *
     * @return Collection<int, array{icon: string, tone: string, text: string}>
     */
    public function insights(int $householdId, ReportPeriod $period, ?CarbonInterface $reference = null): Collection
    {
        $window = $period->resolve($reference ?? Carbon::now(config('app.timezone')));

        $current = $this->movements->rangeTotals($householdId, $window['from'], $window['to']);
        $previous = $this->movements->rangeTotals($householdId, $window['previous_from'], $window['previous_to']);
        $previousLabel = $this->rangeLabel($window['previous_from'], $window['previous_to']);

        $insights = collect();

        // 1. Gasto total contra el período anterior (ejemplo de la épica:
        //    "Este mes gastaste $150.000 menos").
        if ($previous['expenses'] > 0) {
            $change = ($current['expenses'] - $previous['expenses']) / $previous['expenses'];

            if (abs($change) >= self::TOTAL_CHANGE_THRESHOLD) {
                $more = $change > 0;
                $diff = money(abs($current['expenses'] - $previous['expenses']));

                $insights->push([
                    'icon' => $more ? 'bi-graph-up-arrow' : 'bi-graph-down-arrow',
                    'tone' => $more ? 'warning' : 'success',
                    'text' => "Gastaste {$diff} ".($more ? 'más' : 'menos')." que en {$previousLabel}.",
                ]);
            }
        }

        // 2 y 3. Mayor subida y mayor bajada por categoría ("Alimentación
        //    aumentó 18 % respecto al mes anterior").
        $currentByCategory = $this->movements->expensesByCategory($householdId, $window['from'], $window['to']);
        $previousByCategory = $this->movements->expensesByCategory($householdId, $window['previous_from'], $window['previous_to']);
        [$risen, $fallen] = $this->categoryChanges($currentByCategory, $previousByCategory);

        if ($topRise = $risen->sortByDesc('percent')->first()) {
            $insights->push([
                'icon' => 'bi-arrow-up-right-circle',
                'tone' => 'warning',
                'text' => "«{$topRise['name']}» aumentó ".percent($topRise['percent'])." respecto a {$previousLabel}.",
            ]);
        }

        if ($topFall = $fallen->sortByDesc('percent')->first()) {
            $insights->push([
                'icon' => 'bi-arrow-down-right-circle',
                'tone' => 'success',
                'text' => 'Gastaste '.percent($topFall['percent'])." menos en «{$topFall['name']}».",
            ]);
        }

        // 4. Categoría dominante ("Transporte representa 12 % de tus gastos").
        if ($current['expenses'] > 0 && ($top = $currentByCategory->first()) !== null) {
            $share = round($top['total'] / $current['expenses'] * 100, 1);

            if ($share >= self::TOP_CATEGORY_SHARE) {
                $insights->push([
                    'icon' => 'bi-pie-chart',
                    'tone' => 'finlia',
                    'text' => "«{$top['name']}» representa el ".percent($share).' de tus gastos del período.',
                ]);
            }
        }

        // 5. Balance en rojo: un hecho, no un consejo.
        if ($current['incomes'] > 0 && $current['balance'] < 0) {
            $insights->push([
                'icon' => 'bi-exclamation-triangle',
                'tone' => 'danger',
                'text' => 'Los gastos del período superaron los ingresos: balance '.money($current['balance']).'.',
            ]);
        }

        return $insights->take(self::MAX_INSIGHTS)->values();
    }

    /**
     * Movimientos del rango para la exportación (mismo orden que la pantalla
     * de Movimientos: recientes primero).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function exportRows(int $householdId, CarbonInterface $from, CarbonInterface $to): Collection
    {
        // La exportación no puede truncarse en silencio: el tope de 200 de
        // `filtered()` protege la pantalla de navegación, no un archivo que
        // el usuario va a conciliar. 10.000 filas cubre con holgura un año a
        // escala personal y sigue siendo un límite de memoria prudente.
        return $this->movements->filtered($householdId, [
            'from' => Carbon::parse($from)->toDateString(),
            'to' => Carbon::parse($to)->toDateString(),
        ], 10000);
    }

    /**
     * Subidas y bajadas por categoría entre dos períodos, ya filtradas por
     * el umbral. Solo categorías presentes en ambos lados y con base > 0:
     * sin gasto previo no hay porcentaje honesto que afirmar.
     *
     * @param  Collection<int, array<string, mixed>>  $current
     * @param  Collection<int, array<string, mixed>>  $previous
     * @return array{0: Collection, 1: Collection}
     *                                             0: subidas (name, percent) · 1: bajadas (name, percent)
     */
    private function categoryChanges(Collection $current, Collection $previous): array
    {
        $risen = collect();
        $fallen = collect();

        foreach ($current as $category) {
            $before = (float) ($previous->firstWhere('category_id', $category['category_id'])['total'] ?? 0);

            if ($before <= 0) {
                continue;
            }

            $change = ($category['total'] - $before) / $before;

            if ($change >= self::CATEGORY_CHANGE_THRESHOLD) {
                $risen->push(['name' => $category['name'], 'percent' => round($change * 100, 1)]);
            } elseif ($change <= -self::CATEGORY_CHANGE_THRESHOLD) {
                $fallen->push(['name' => $category['name'], 'percent' => round(abs($change) * 100, 1)]);
            }
        }

        return [$risen, $fallen];
    }

    /**
     * Diferencia entre el valor actual y el anterior: absoluta y, solo si la
     * base anterior existe (> 0), en porcentaje.
     *
     * @return array{absolute: float, percent: ?float}
     */
    private function delta(float $current, float $previous): array
    {
        return [
            'absolute' => round($current - $previous, 2),
            'percent' => $previous > 0 ? round(($current - $previous) / $previous * 100, 1) : null,
        ];
    }

    /**
     * Etiqueta legible de un rango: "agosto 2026", "jun–ago 2026" o
     * "nov 25 – ago 26".
     */
    private function rangeLabel(CarbonInterface $from, CarbonInterface $to): string
    {
        $from = Carbon::parse($from);
        $to = Carbon::parse($to);

        if ($from->isSameMonth($to)) {
            return $from->locale('es')->isoFormat('MMMM YYYY');
        }

        if ($from->isSameYear($to)) {
            return $from->locale('es')->isoFormat('MMM').'–'.$to->locale('es')->isoFormat('MMM YYYY');
        }

        return $from->locale('es')->isoFormat('MMM YY').' – '.$to->locale('es')->isoFormat('MMM YY');
    }
}
