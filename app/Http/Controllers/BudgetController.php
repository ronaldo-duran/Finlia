<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\BudgetScope;
use App\Enums\CategoryType;
use App\Http\Requests\Budget\StoreBudgetRequest;
use App\Http\Requests\Budget\UpdateBudgetRequest;
use App\Models\Budget;
use App\Models\Category;
use App\Services\BudgetCalculatorService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Presupuestos del hogar y respuesta a "¿cuánto puedo gastar?".
 * Controlador fino: todo el cálculo vive en BudgetCalculatorService (ADR-0010).
 */
class BudgetController extends Controller
{
    public function __construct(private readonly BudgetCalculatorService $calculator) {}

    /**
     * Panel de presupuestos del período consultado (semana / mes / próximo mes).
     */
    public function index(Request $request): View|RedirectResponse
    {
        $household = active_household();

        // Defensivo: un usuario autenticado siempre tiene hogar (ADR-0011).
        if ($household === null) {
            return redirect()->route('households.create');
        }

        $this->authorize('viewAny', Budget::class);

        $scope = BudgetScope::tryFrom((string) $request->query('periodo')) ?? BudgetScope::Month;
        $summary = $this->calculator->summary($household->id, $scope);

        // Presupuestos del mes de referencia, para el listado editable.
        $budgets = $household->budgets()
            ->forMonth($summary['year'], $summary['month'])
            ->with('category')
            ->get()
            ->sortBy(fn (Budget $b) => [$b->category_id === null ? 0 : 1, $b->category?->name ?? ''])
            ->values();

        return view('budgets.index', [
            'summary' => $summary,
            'budgets' => $budgets,
            'scope' => $scope,
            'monthLabel' => $this->monthLabel($summary['year'], $summary['month']),
        ]);
    }

    public function create(Request $request): View|RedirectResponse
    {
        if (active_household() === null) {
            return redirect()->route('households.create');
        }

        $this->authorize('create', Budget::class);

        $reference = $this->referenceMonth($request->query('periodo'));

        return view('budgets.create', array_merge($this->formOptions(), [
            'year' => $reference->year,
            'month' => $reference->month,
        ]));
    }

    public function store(StoreBudgetRequest $request): RedirectResponse
    {
        $this->authorize('create', Budget::class);

        $budget = active_household()->budgets()->create($request->validatedData());

        return redirect()
            ->route('budgets.index', $this->scopeQuery($budget))
            ->with('status', __('Presupuesto guardado.'));
    }

    public function edit(Request $request, Budget $budget): View
    {
        $this->authorize('update', $budget);

        return view('budgets.edit', array_merge($this->formOptions(), [
            'budget' => $budget,
            'monthLabel' => $this->monthLabel($budget->year, $budget->month),
        ]));
    }

    public function update(UpdateBudgetRequest $request, Budget $budget): RedirectResponse
    {
        $this->authorize('update', $budget);

        $budget->update($request->validatedData());

        return redirect()
            ->route('budgets.index', $this->scopeQuery($budget))
            ->with('status', __('Presupuesto actualizado.'));
    }

    public function destroy(Request $request, Budget $budget): RedirectResponse
    {
        $this->authorize('delete', $budget);

        $query = $this->scopeQuery($budget);
        $budget->delete();

        return redirect()
            ->route('budgets.index', $query)
            ->with('status', __('Presupuesto eliminado.'));
    }

    /**
     * Categorías de gasto disponibles para presupuestar (globales + del hogar).
     *
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'categories' => Category::forHousehold(active_household_id())
                ->where('type', CategoryType::Expense->value)
                ->orderBy('name')
                ->get(),
        ];
    }

    /**
     * Mes de referencia según el período consultado, para precargar el formulario.
     */
    private function referenceMonth(mixed $periodo): Carbon
    {
        $today = Carbon::now(config('app.timezone'));

        return BudgetScope::tryFrom((string) $periodo) === BudgetScope::NextMonth
            ? $today->addMonthNoOverflow()->startOfMonth()
            : $today;
    }

    /**
     * Devuelve al listado en el período al que pertenece el presupuesto tocado,
     * para no perder el contexto tras guardar.
     *
     * @return array<string, string>
     */
    private function scopeQuery(Budget $budget): array
    {
        $nextMonth = Carbon::now(config('app.timezone'))->addMonthNoOverflow();

        return $budget->year === $nextMonth->year && $budget->month === $nextMonth->month
            ? ['periodo' => BudgetScope::NextMonth->value]
            : [];
    }

    private function monthLabel(int $year, int $month): string
    {
        return Carbon::createFromDate($year, $month, 1)->locale('es')->isoFormat('MMMM [de] YYYY');
    }
}
