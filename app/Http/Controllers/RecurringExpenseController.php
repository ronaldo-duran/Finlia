<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\CategoryType;
use App\Http\Requests\RecurringExpense\StoreRecurringExpenseRequest;
use App\Http\Requests\RecurringExpense\UpdateRecurringExpenseRequest;
use App\Models\Category;
use App\Models\RecurringExpense;
use App\Services\RecurringExpenseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Gastos recurrentes y obligaciones futuras del hogar (Épica 5).
 * Controlador fino: la lógica de fechas y cálculo vive en
 * RecurringExpenseService (ADR-0010).
 */
class RecurringExpenseController extends Controller
{
    public function __construct(private readonly RecurringExpenseService $recurring) {}

    public function index(Request $request): View|RedirectResponse
    {
        $household = active_household();

        // Defensivo: un usuario autenticado siempre tiene hogar (ADR-0011).
        if ($household === null) {
            return redirect()->route('households.create');
        }

        $this->authorize('viewAny', RecurringExpense::class);

        return view('recurring-expenses.index', [
            'upcoming' => $this->recurring->upcoming($household->id),
            'all' => $household->recurringExpenses()
                ->with(['category', 'account'])
                ->orderByDesc('is_active')
                ->orderBy('next_date')
                ->get(),
            'totalMonthlySavings' => $this->recurring->totalMonthlySavings($household->id),
            'categories' => Category::forHousehold($household->id)
                ->where('type', CategoryType::Expense->value)
                ->orderBy('name')
                ->get(),
            'accounts' => $household->accounts()
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(StoreRecurringExpenseRequest $request): RedirectResponse
    {
        $this->authorize('create', RecurringExpense::class);

        active_household()->recurringExpenses()->create($request->validatedData());

        return redirect()
            ->route('recurring-expenses.index')
            ->with('status', __('Gasto recurrente añadido.'));
    }

    public function update(UpdateRecurringExpenseRequest $request, RecurringExpense $recurringExpense): RedirectResponse
    {
        $this->authorize('update', $recurringExpense);

        $recurringExpense->update($request->validatedData());

        return redirect()
            ->route('recurring-expenses.index')
            ->with('status', __('Gasto recurrente actualizado.'));
    }

    public function destroy(Request $request, RecurringExpense $recurringExpense): RedirectResponse
    {
        $this->authorize('delete', $recurringExpense);

        $recurringExpense->delete();

        return redirect()
            ->route('recurring-expenses.index')
            ->with('status', __('Gasto recurrente ":name" eliminado.', ['name' => $recurringExpense->name]));
    }

    /**
     * "Marcar pagado": registra el gasto real (si tiene cuenta) y avanza la
     * próxima fecha, sin duplicar la obligación en el cálculo.
     */
    public function markPaid(Request $request, RecurringExpense $recurringExpense): RedirectResponse
    {
        $this->authorize('markPaid', $recurringExpense);

        $expense = $this->recurring->markAsPaid($recurringExpense, $request->user());

        $status = $expense !== null
            ? __('Pago de ":name" registrado y próxima fecha avanzada.', ['name' => $recurringExpense->name])
            : __('":name" marcado como pagado. Como no tiene cuenta asociada, registra el gasto desde “Registrar gasto” si quieres verlo en movimientos.', ['name' => $recurringExpense->name]);

        return redirect()
            ->route('recurring-expenses.index')
            ->with('status', $status);
    }
}
