<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\CategoryType;
use App\Http\Requests\Expense\StoreExpenseRequest;
use App\Http\Requests\Expense\UpdateExpenseRequest;
use App\Models\Account;
use App\Models\Category;
use App\Models\Expense;
use App\Services\BudgetCalculatorService;
use App\Services\CompulsiveSurveyService;
use App\Services\MovementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ExpenseController extends Controller
{
    public function __construct(
        private readonly MovementService $movements,
        private readonly BudgetCalculatorService $budgets,
        private readonly CompulsiveSurveyService $surveys,
    ) {}

    public function create(Request $request): View|RedirectResponse
    {
        if (active_household() === null) {
            return redirect()->route('households.create');
        }

        $this->authorize('create', Expense::class);

        $liquidity = $this->budgets->liquidity(active_household_id());

        return view('expenses.create', array_merge($this->formOptions(), [
            'available' => $liquidity['available'],
            'availableUntil' => $liquidity['until'],
        ]));
    }

    public function store(StoreExpenseRequest $request): RedirectResponse
    {
        $this->authorize('create', Expense::class);

        $household = active_household();
        $user = $request->user();
        $expense = $this->movements->createExpense(
            $request->validatedData(),
            $household,
            $user,
        );

        if ($this->surveys->shouldOffer($household, $expense, $user)) {
            session()->flash('compulsive_survey_expense_id', $expense->id);
            $this->surveys->markShownToday($user);
        }

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

        $categories = Category::forHousehold($householdId)
            ->where('type', CategoryType::Expense->value)
            ->orderBy('name')
            ->get();

        $debtsCategoryId = $categories
            ->first(fn ($c) => mb_strtolower($c->name) === 'deudas')?->id;

        return [
            'accounts' => Account::where('household_id', $householdId)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
            'categories' => $categories,
            'debtsCategoryId' => $debtsCategoryId,
        ];
    }
}
