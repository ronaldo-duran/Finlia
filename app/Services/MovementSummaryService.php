<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Expense;
use App\Models\Income;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Agregaciones que combinan ingresos y gastos (ADR-0001).
 *
 * Centraliza los totales del dashboard, gastos por categoría, tendencia
 * mensual, últimos movimientos y la lista filtrada. Así no se duplica
 * lógica de UNION entre controladores.
 *
 * Seam (ADR-0010): no depende de la capa HTTP.
 */
class MovementSummaryService
{
    /**
     * Totales (ingresos, gastos, balance) de un mes concreto.
     *
     * @return array{incomes: float, expenses: float, balance: float}
     */
    public function monthTotals(int $householdId, int $year, int $month): array
    {
        [$from, $to] = $this->monthBounds($year, $month);

        return $this->rangeTotals($householdId, $from, $to);
    }

    /**
     * Totales (ingresos, gastos, balance) de un rango arbitrario.
     *
     * Base de los reportes por período (Épica 8): mes, trimestre, año…
     *
     * @return array{incomes: float, expenses: float, balance: float}
     */
    public function rangeTotals(int $householdId, CarbonInterface $from, CarbonInterface $to): array
    {
        $incomes = (float) Income::where('household_id', $householdId)
            ->whereBetween('date', [$from, $to])
            ->sum('amount');

        $expenses = (float) Expense::where('household_id', $householdId)
            ->whereBetween('date', [$from, $to])
            ->sum('amount');

        return [
            'incomes' => $incomes,
            'expenses' => $expenses,
            'balance' => $incomes - $expenses,
        ];
    }

    /**
     * Gastos agrupados por categoría en un rango.
     *
     * Con $top, las categorías por debajo del top N se pliegan en una fila
     * "Otras" (gris neutro): una torta con 15 porciones no se lee. Sin $top
     * devuelve todas — los cálculos (p. ej. insights) sí necesitan la lista
     * completa.
     *
     * @return Collection<int, array{category_id: ?int, name: ?string, color: ?string, total: float}>
     */
    public function expensesByCategory(int $householdId, CarbonInterface $from, CarbonInterface $to, ?int $top = null): Collection
    {
        $rows = DB::table('expenses')
            ->leftJoin('categories', 'categories.id', '=', 'expenses.category_id')
            ->selectRaw('categories.id as category_id, categories.name as name, categories.color as color, SUM(expenses.amount) as total')
            ->where('expenses.household_id', $householdId)
            ->whereNull('expenses.deleted_at') // soft-deleted fuera del cálculo
            ->whereBetween('expenses.date', [$from, $to])
            ->groupBy('categories.id', 'categories.name', 'categories.color')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'category_id' => $row->category_id,
                'name' => $row->name ?? 'Sin categoría',
                'color' => $row->color,
                'total' => (float) $row->total,
            ]);

        return $top !== null ? $this->foldIntoOthers($rows, $top) : $rows;
    }

    /**
     * Top N categorías + una fila "Otras" con la suma del resto. Sin resto
     * no hay fila: 5 categorías no generan una sexta vacía.
     *
     * @param  Collection<int, array{category_id: ?int, name: ?string, color: ?string, total: float}>  $rows
     * @return Collection<int, array{category_id: ?int, name: ?string, color: ?string, total: float}>
     */
    private function foldIntoOthers(Collection $rows, int $top): Collection
    {
        if ($rows->count() <= $top) {
            return $rows;
        }

        return $rows
            ->take($top)
            ->values()
            ->push([
                'category_id' => null,
                'name' => 'Otras',
                // Gris neutro del sistema: "Otras" no compite con los
                // colores reales de las categorías.
                'color' => '#adb5bd',
                'total' => (float) $rows->skip($top)->sum('total'),
            ]);
    }

    /**
     * Tendencia ingresos vs gastos de los últimos N meses (incluido el actual).
     *
     * @return list<array{label: string, incomes: float, expenses: float}>
     */
    public function monthlyTrend(int $householdId, int $months = 6): array
    {
        $cursor = Carbon::now(config('app.timezone'))->startOfMonth();
        $trend = [];

        for ($i = 0; $i < $months; $i++) {
            $point = $cursor->copy();
            $totals = $this->monthTotals($householdId, $point->year, $point->month);

            array_unshift($trend, [
                'label' => $point->locale('es')->isoFormat('MMM YY'),
                'incomes' => $totals['incomes'],
                'expenses' => $totals['expenses'],
            ]);

            $cursor->subMonth();
        }

        return $trend;
    }

    /**
     * Últimos movimientos (ingresos + gastos) ordenados por fecha desc.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function recentMovements(int $householdId, int $limit = 8): Collection
    {
        return $this->filtered($householdId, [], $limit);
    }

    /**
     * Lista combinada y filtrada de movimientos.
     *
     * @param  array{type?: ?string, category_id?: ?int, account_id?: ?int, user_id?: ?int, from?: ?string, to?: ?string}  $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function filtered(int $householdId, array $filters = [], ?int $limit = null, int $offset = 0): Collection
    {
        $type = $filters['type'] ?? null;
        $limit ??= 20; // página por defecto de la lista; el llamador debe ser explícito

        $movements = collect();
        // Para paginar la mezcla hay que traer offset+limit de CADA tabla:
        // los rangos globales se intercalan con los de cada tipo.
        $fetch = $offset + $limit;

        if ($type === null || $type === 'income') {
            $this->applyFilters(Income::where('household_id', $householdId), $filters)
                ->with(['category', 'account', 'user'])
                // `date` es DATE (sin hora): sin desempate, los movimientos del
                // mismo día quedan en un orden arbitrario y el recién creado
                // puede caerse del LIMIT. created_at sí lleva hora.
                ->orderByDesc('date')
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->take($fetch)
                ->get()
                ->each(fn (Income $i) => $movements->push($this->normalize($i, 'income')));
        }

        if ($type === null || $type === 'expense') {
            $this->applyFilters(Expense::where('household_id', $householdId), $filters)
                ->with(['category', 'account', 'user'])
                ->orderByDesc('date')
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->take($fetch)
                ->get()
                ->each(fn (Expense $e) => $movements->push($this->normalize($e, 'expense')));
        }

        // Se trae $fetch de cada tabla: al mezclarlas hay que reordenar con el
        // mismo criterio y recortar otra vez, o el llamador recibe el doble.
        return $movements
            ->sortByDesc(fn (array $m) => $this->sortKey($m))
            ->skip($offset)
            ->take($limit)
            ->values();
    }

    /**
     * Página de la lista para "Cargar más": corta en $limit pero NUNCA a
     * mitad de un día, para que los grupos de la pantalla no se partan
     * entre páginas. Devuelve [movimientos de la página, hay más páginas].
     *
     * @param  array{type?: ?string, category_id?: ?int, account_id?: ?int, user_id?: ?int, from?: ?string, to?: ?string}  $filters
     * @return array{0: Collection<int, array<string, mixed>>, 1: bool}
     */
    public function filteredPage(int $householdId, array $filters, int $offset, int $limit): array
    {
        // Holgura extra en una sola consulta: la que hace falta para cerrar
        // el día cortado y para saber si hay más páginas. 50 cubre de sobra
        // lo que resta de un día a escala personal; si un día extremo la
        // excede, solo ese grupo queda partido (cosmético, no pierde datos).
        $window = $this->filtered($householdId, $filters, $limit + 50, $offset);

        $page = $window->take($limit)->values();

        if ($page->isNotEmpty() && $window->count() > $page->count()) {
            $lastDay = $page->last()['date']->format('Y-m-d');

            $page = $page
                ->concat(
                    $window->skip($page->count())
                        ->takeWhile(fn (array $m) => $m['date']->format('Y-m-d') === $lastDay)
                )
                ->values();
        }

        // Más páginas: lo que quede del window, o que el window haya llenado
        // el tope pedido (no se puede saber sin pedir otra página).
        $hasMore = $window->skip($page->count())->isNotEmpty() || $window->count() === $limit + 50;

        return [$page, $hasMore];
    }

    /**
     * Totales (ingresos, gastos, balance) de TODO lo que coincide con los
     * filtros, sin paginar: el "Balance del filtro" de la pantalla no debe
     * cambiar según cuántas páginas haya cargado el usuario.
     *
     * @param  array{type?: ?string, category_id?: ?int, account_id?: ?int, user_id?: ?int, from?: ?string, to?: ?string}  $filters
     * @return array{incomes: float, expenses: float, balance: float}
     */
    public function filteredTotals(int $householdId, array $filters = []): array
    {
        $type = $filters['type'] ?? null;

        $incomes = $type === 'expense'
            ? 0.0
            : (float) $this->applyFilters(Income::where('household_id', $householdId), $filters)->sum('amount');

        $expenses = $type === 'income'
            ? 0.0
            : (float) $this->applyFilters(Expense::where('household_id', $householdId), $filters)->sum('amount');

        return [
            'incomes' => $incomes,
            'expenses' => $expenses,
            'balance' => $incomes - $expenses,
        ];
    }

    /**
     * Filtros comunes de la lista, aplicados igual en ingresos y gastos.
     *
     * @param  array{type?: ?string, category_id?: ?int, account_id?: ?int, user_id?: ?int, from?: ?string, to?: ?string}  $filters
     */
    private function applyFilters(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['category_id'] ?? null, fn ($q, $id) => $q->where('category_id', $id))
            ->when($filters['account_id'] ?? null, fn ($q, $id) => $q->where('account_id', $id))
            ->when($filters['user_id'] ?? null, fn ($q, $id) => $q->where('user_id', $id))
            ->when($filters['from'] ?? null, fn ($q, $d) => $q->where('date', '>=', $d))
            ->when($filters['to'] ?? null, fn ($q, $d) => $q->where('date', '<=', $d));
    }

    /**
     * Clave de orden de un movimiento: día, luego hora de registro, luego id.
     *
     * Se compone como cadena de ancho fijo para que la comparación sea
     * lexicográfica y equivalga a ordenar por las tres columnas.
     */
    private function sortKey(array $m): string
    {
        return $m['date']->format('Ymd')
            .str_pad((string) $m['registered_at']?->getTimestamp(), 12, '0', STR_PAD_LEFT)
            .str_pad((string) $m['id'], 12, '0', STR_PAD_LEFT);
    }

    /**
     * @return array<string, mixed>
     */
    private function normalize(Income|Expense $m, string $type): array
    {
        return [
            'type' => $type,
            'id' => $m->id,
            'amount' => (float) $m->amount,
            'date' => $m->date,
            // Hora real de registro: `date` no la tiene (columna DATE).
            'registered_at' => $m->created_at,
            'description' => $m->description,
            'category_name' => $m->category?->name ?? 'Sin categoría',
            'category_color' => $m->category?->color,
            'account_name' => $m->account?->name,
            'user_name' => $m->user?->name,
            'payment_method' => $type === 'expense' && $m instanceof Expense ? $m->payment_method?->label() : null,
        ];
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function monthBounds(int $year, int $month): array
    {
        $start = Carbon::createFromDate($year, $month, 1)->startOfDay();

        return [$start, $start->copy()->endOfMonth()];
    }
}
