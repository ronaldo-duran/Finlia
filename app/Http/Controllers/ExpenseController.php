<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\CategoryType;
use App\Http\Requests\Expense\StoreExpenseRequest;
use App\Http\Requests\Expense\UpdateExpenseRequest;
use App\Models\Account;
use App\Models\Category;
use App\Models\Expense;
use App\Services\MovementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ExpenseController extends Controller
{
    public function __construct(private readonly MovementService $movements) {}

    public function create(Request $request): View
    {
        return view('expenses.create', $this->formOptions());
    }

    public function store(StoreExpenseRequest $request): RedirectResponse
    {
        $this->authorize('create', Expense::class);

        $this->movements->createExpense(
            $request->validatedData(),
            active_household(),
            $request->user(),
        );

        return redirect()
            ->route('dashboard')
            ->with('status', __('Gasto registrado.'));
    }

    public function edit(Request $request, Expense $expense): View
    {
        $this->authorize('update', $expense);

        return view('expenses.edit', array_merge(['expense' => $expense], $this->formOptions()));
    }

    public function update(UpdateExpenseRequest $request, Expense $expense): RedirectResponse
    {
        $this->authorize('update', $expense);

        $this->movements->updateExpense($expense, $request->validatedData());

        return redirect()
            ->route('movements.index')
            ->with('status', __('Gasto actualizado.'));
    }

    public function destroy(Request $request, Expense $expense): RedirectResponse
    {
        $this->authorize('delete', $expense);

        $this->movements->deleteExpense($expense);

        return redirect()
            ->route('movements.index')
            ->with('status', __('Gasto eliminado.'));
    }

    /**
     * Listas para los selects del formulario (acotadas al hogar activo).
     *
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        $householdId = active_household_id();

        return [
            'accounts' => Account::where('household_id', $householdId)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
            'categories' => Category::forHousehold($householdId)
                ->where('type', CategoryType::Expense->value)
                ->orderBy('name')
                ->get(),
        ];
    }
}
