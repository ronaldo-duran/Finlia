<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Category;
use App\Services\MovementSummaryService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Vista unificada de movimientos (ingresos + gastos) con filtros.
 * La combinación de ambas tablas se centraliza en MovementSummaryService (ADR-0001).
 */
class MovementsController extends Controller
{
    public function __construct(private readonly MovementSummaryService $summary) {}

    public function index(Request $request): View
    {
        $household = active_household();
        $householdId = $household->id;

        $filters = $this->filters($request);

        $movements = $this->summary->filtered($householdId, $filters);

        return view('movements.index', [
            'movements' => $movements,
            'filters' => $filters,
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
        if (! in_array($type, ['income', 'expense'], true)) {
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
