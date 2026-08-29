<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Expense;
use App\Models\Income;
use Carbon\Carbon;
use Carbon\CarbonInterface;
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
     * @return Collection<int, array{category_id: ?int, name: ?string, color: ?string, total: float}>
     */
    public function expensesByCategory(int $householdId, CarbonInterface $from, CarbonInterface $to): Collection
    {
        return DB::table('expenses')
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
    public function filtered(int $householdId, array $filters = [], ?int $limit = null): Collection
    {
        $type = $filters['type'] ?? null;
        $limit ??= 200; // tope de seguridad; escala personal es pequeña

        $movements = collect();

        if ($type === null || $type === 'income') {
            Income::where('household_id', $householdId)
                ->when($filters['category_id'] ?? null, fn ($q, $id) => $q->where('category_id', $id))
                ->when($filters['account_id'] ?? null, fn ($q, $id) => $q->where('account_id', $id))
                ->when($filters['user_id'] ?? null, fn ($q, $id) => $q->where('user_id', $id))
                ->when($filters['from'] ?? null, fn ($q, $d) => $q->where('date', '>=', $d))
                ->when($filters['to'] ?? null, fn ($q, $d) => $q->where('date', '<=', $d))
                ->with(['category', 'account', 'user'])
                // `date` es DATE (sin hora): sin desempate, los movimientos del
                // mismo día quedan en un orden arbitrario y el recién creado
                // puede caerse del LIMIT. created_at sí lleva hora.
                ->orderByDesc('date')
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->take($limit)
                ->get()
                ->each(fn (Income $i) => $movements->push($this->normalize($i, 'income')));
        }

        if ($type === null || $type === 'expense') {
            Expense::where('household_id', $householdId)
                ->when($filters['category_id'] ?? null, fn ($q, $id) => $q->where('category_id', $id))
                ->when($filters['account_id'] ?? null, fn ($q, $id) => $q->where('account_id', $id))
                ->when($filters['user_id'] ?? null, fn ($q, $id) => $q->where('user_id', $id))
                ->when($filters['from'] ?? null, fn ($q, $d) => $q->where('date', '>=', $d))
                ->when($filters['to'] ?? null, fn ($q, $d) => $q->where('date', '<=', $d))
                ->with(['category', 'account', 'user'])
                ->orderByDesc('date')
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->take($limit)
                ->get()
                ->each(fn (Expense $e) => $movements->push($this->normalize($e, 'expense')));
        }

        // Se toman $limit de cada tabla: al mezclarlas hay que reordenar con el
        // mismo criterio y recortar otra vez, o el llamador recibe el doble.
        return $movements
            ->sortByDesc(fn (array $m) => $this->sortKey($m))
            ->take($limit)
            ->values();
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
