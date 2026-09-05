<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Category;
use App\Services\MovementSummaryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Vista unificada de movimientos (ingresos + gastos) con filtros.
 * La combinación de ambas tablas se centraliza en MovementSummaryService (ADR-0001).
 */
class MovementsController extends Controller
{
    /** Movimientos por página: la lista carga de a 20 con "Cargar más". */
    private const PAGE_SIZE = 20;

    public function __construct(private readonly MovementSummaryService $summary) {}

    public function index(Request $request): View|RedirectResponse
    {
        $household = active_household();

        // Defensivo: un usuario autenticado siempre tiene hogar (ADR-0011).
        if ($household === null) {
            return redirect()->route('households.create');
        }

        $householdId = $household->id;

        $filters = $this->filters($request);
        $offset = max(0, (int) $request->query('offset'));

        [$movements, $hasMore] = $this->summary->filteredPage($householdId, $filters, $offset, self::PAGE_SIZE);

        $list = [
            'movements' => $movements,
            'hasMore' => $hasMore,
            'nextOffset' => $offset + $movements->count(),
        ];

        // "Cargar más": misma ruta con offset, devuelve solo los grupos
        // nuevos para anexarlos a la lista que ya está en pantalla.
        if ($request->ajax()) {
            return view('movements._groups', $list);
        }

        return view('movements.index', $list + [
            'filters' => $filters,
            // El balance es de TODO el filtro, no de la página visible.
            'filterTotals' => $this->summary->filteredTotals($householdId, $filters),
            'categories' => Category::forHousehold($householdId)->orderBy('name')->get(),
            'accounts' => Account::where('household_id', $householdId)->orderBy('name')->get(),
            'members' => $household->members()->orderBy('name')->get(),
        ]);
    }

    /**
     * Extrae y normaliza los filtros del request.
     *
     * @return array{type: ?string, category_id: ?int, account_id: ?int, user_id: ?int, from: ?string, to: ?string}
     */
    private function filters(Request $request): array
    {
        $type = $request->input('type');
        if (! in_array($type, ['income', 'expense', 'transfer'], true)) {
            $type = null;
        }

        return [
            'type' => $type,
            'category_id' => $request->filled('category_id') ? (int) $request->input('category_id') : null,
            'account_id' => $request->filled('account_id') ? (int) $request->input('account_id') : null,
            'user_id' => $request->filled('user_id') ? (int) $request->input('user_id') : null,
            'from' => $request->filled('from') ? (string) $request->input('from') : null,
            'to' => $request->filled('to') ? (string) $request->input('to') : null,
        ];
    }
}
