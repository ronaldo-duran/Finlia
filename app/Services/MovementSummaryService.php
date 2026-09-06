<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Expense;
use App\Models\Income;
use App\Models\Transfer;
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
    /** Color de la marca para categorías sin color propio. */
    private const FALLBACK_COLOR = '#0b3f44';

    /**
     * Memoria de agregaciones ya calculadas dentro de **esta** petición.
     *
     * `/reportes` pedía los mismos números varias veces: `overview()` e
     * `insights()` calculaban cada uno los totales del período y del anterior
     * (4 sumas, 2 redundantes), y `expensesByCategory` corría dos veces para
     * el mismo rango (lista completa + top 5).
     *
     * El servicio no es singleton: la instancia muere con la petición, así que
     * no hay riesgo de servir datos rancios entre peticiones. Dentro de una
     * misma petición estas agregaciones son de solo lectura.
     *
     * @var array<string, mixed>
     */
    private array $memo = [];

    /**
     * @template T
     *
     * @param  callable(): T  $compute
     * @return T
     */
    private function remember(string $key, callable $compute): mixed
    {
        return $this->memo[$key] ??= $compute();
    }

    private function rangeKey(string $prefix, int $householdId, CarbonInterface $from, CarbonInterface $to): string
    {
        return $prefix.'|'.$householdId.'|'.$from->toDateTimeString().'|'.$to->toDateTimeString();
    }

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
        return $this->remember($this->rangeKey('totals', $householdId, $from, $to), function () use ($householdId, $from, $to): array {
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
        });
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
        // Se memoiza la lista completa; el plegado en "Otros" es sobre memoria,
        // así que pedir el top-N no repite la consulta.
        $rows = $this->remember(
            $this->rangeKey('by-category', $householdId, $from, $to),
            fn (): Collection => DB::table('expenses')
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
                ]),
        );

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
        $end = Carbon::now(config('app.timezone'))->startOfMonth();
        $start = $end->copy()->subMonthsNoOverflow($months - 1);

        $totals = $this->monthlyTotals($householdId, $start, $end->copy()->endOfMonth());

        $trend = [];
        $cursor = $start->copy();

        for ($i = 0; $i < $months; $i++) {
            $key = $cursor->format('Y-m');

            $trend[] = [
                'label' => $cursor->locale('es')->isoFormat('MMM YY'),
                'incomes' => $totals[$key]['incomes'] ?? 0.0,
                'expenses' => $totals[$key]['expenses'] ?? 0.0,
            ];

            $cursor->addMonthNoOverflow();
        }

        return $trend;
    }

    /**
     * Forma que espera Chart.js para la torta de gastos por categoría.
     *
     * Vive aquí (y no en cada controlador) porque Panel y Reportes pintan la
     * misma torta: tenerlo duplicado hacía que el color de reserva pudiera
     * divergir entre pantallas.
     *
     * @param  Collection<int, array{name: string, total: float, color: ?string}>  $byCategory
     * @return array{labels: list<string>, amounts: list<float>, colors: list<string>}
     */
    public static function categoryChartData(Collection $byCategory): array
    {
        return [
            'labels' => $byCategory->pluck('name')->all(),
            'amounts' => $byCategory->pluck('total')->all(),
            'colors' => $byCategory->map(fn (array $c): string => $c['color'] ?? self::FALLBACK_COLOR)->all(),
        ];
    }

    /**
     * Totales de ingresos y gastos agrupados por mes natural, en **2 queries**
     * (una por tabla) en lugar de dos por cada mes del rango.
     *
     * @return array<string, array{incomes: float, expenses: float}> mapa 'YYYY-MM' => totales
     */
    public function monthlyTotals(int $householdId, CarbonInterface $from, CarbonInterface $to): array
    {
        $monthKey = $this->monthKeyExpression();

        $sumByMonth = fn (string $model): array => $model::where('household_id', $householdId)
            ->whereBetween('date', [$from, $to])
            ->selectRaw("{$monthKey} as ym, COALESCE(SUM(amount), 0) as total")
            ->groupBy(DB::raw($monthKey))
            ->pluck('total', 'ym')
            ->map(fn ($total): float => (float) $total)
            ->all();

        $incomes = $sumByMonth(Income::class);
        $expenses = $sumByMonth(Expense::class);

        $totals = [];

        foreach (array_unique([...array_keys($incomes), ...array_keys($expenses)]) as $ym) {
            $totals[$ym] = [
                'incomes' => $incomes[$ym] ?? 0.0,
                'expenses' => $expenses[$ym] ?? 0.0,
            ];
        }

        return $totals;
    }

    /**
     * Expresión SQL que reduce una fecha a 'YYYY-MM'.
     *
     * Depende del motor a propósito: MySQL no tiene `strftime` y SQLite no
     * tiene `DATE_FORMAT`. Producción usa MySQL/MariaDB y los tests SQLite.
     */
    private function monthKeyExpression(): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', `date`)"
            : "DATE_FORMAT(`date`, '%Y-%m')";
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
     * Lista combinada y filtrada de movimientos (ingresos, gastos y transferencias).
     *
     * Las transferencias se incluyen cuando el tipo es null (todos) o 'transfer'.
     * Si se filtra por category_id, las transferencias se excluyen (no tienen categoría).
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

        // Las transferencias no tienen categoría: solo se muestran cuando no
        // hay filtro de categoría y el tipo es null o 'transfer'.
        $includeTransfers = ($type === null || $type === 'transfer')
            && empty($filters['category_id']);

        if ($includeTransfers) {
            $this->applyTransferFilters(Transfer::where('household_id', $householdId), $filters)
                ->with(['fromAccount', 'toAccount', 'user'])
                ->orderByDesc('date')
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->take($fetch)
                ->get()
                ->each(fn (Transfer $t) => $movements->push($this->normalizeTransfer($t)));
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
     * Filtros aplicables a transferencias.
     * Las transferencias no tienen category_id; account_id filtra por ambos extremos.
     *
     * @param  array{type?: ?string, category_id?: ?int, account_id?: ?int, user_id?: ?int, from?: ?string, to?: ?string}  $filters
     */
    private function applyTransferFilters(Builder $query, array $filters): Builder
    {
        return $query
            ->when(
                $filters['account_id'] ?? null,
                fn ($q, $id) => $q->where(fn ($q2) => $q2
                    ->where('from_account_id', $id)
                    ->orWhere('to_account_id', $id))
            )
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
     * Normaliza una transferencia al formato unificado del listado.
     *
     * @return array<string, mixed>
     */
    private function normalizeTransfer(Transfer $t): array
    {
        $fromName = $t->fromAccount?->name ?? '—';
        $toName = $t->toAccount?->name ?? '—';

        return [
            'type' => 'transfer',
            'id' => $t->id,
            'amount' => (float) $t->amount,
            'date' => $t->date,
            'registered_at' => $t->created_at,
            'description' => $t->description ?: 'Transferencia',
            'category_name' => null,
            'category_color' => null,
            'account_name' => "{$fromName} → {$toName}",
            'user_name' => $t->user?->name,
            'payment_method' => null,
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
