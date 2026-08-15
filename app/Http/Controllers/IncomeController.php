<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\CategoryType;
use App\Http\Requests\Income\StoreIncomeRequest;
use App\Http\Requests\Income\UpdateIncomeRequest;
use App\Models\Account;
use App\Models\Category;
use App\Models\Income;
use App\Services\MovementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class IncomeController extends Controller
{
    public function __construct(private readonly MovementService $movements) {}

    public function create(Request $request): View
    {
        return view('incomes.create', $this->formOptions());
    }

    public function store(StoreIncomeRequest $request): RedirectResponse
    {
        $this->authorize('create', Income::class);

        $this->movements->createIncome(
            $request->validatedData(),
            active_household(),
            $request->user(),
        );

        return redirect()
            ->route('dashboard')
            ->with('status', __('Ingreso registrado.'));
    }

    public function edit(Request $request, Income $income): View
    {
        $this->authorize('update', $income);

        return view('incomes.edit', array_merge(['income' => $income], $this->formOptions()));
    }

    public function update(UpdateIncomeRequest $request, Income $income): RedirectResponse
    {
        $this->authorize('update', $income);

        $this->movements->updateIncome($income, $request->validatedData());

        return redirect()
            ->route('movements.index')
            ->with('status', __('Ingreso actualizado.'));
    }

    public function destroy(Request $request, Income $income): RedirectResponse
    {
        $this->authorize('delete', $income);

        $this->movements->deleteIncome($income);

        return redirect()
            ->route('movements.index')
            ->with('status', __('Ingreso eliminado.'));
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
                ->where('type', CategoryType::Income->value)
                ->orderBy('name')
                ->get(),
        ];
    }
}
