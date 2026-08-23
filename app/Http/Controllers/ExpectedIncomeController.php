<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\CategoryType;
use App\Http\Requests\ExpectedIncome\StoreExpectedIncomeRequest;
use App\Http\Requests\ExpectedIncome\UpdateExpectedIncomeRequest;
use App\Models\Category;
use App\Models\ExpectedIncome;
use App\Services\BudgetCalculatorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Ingresos mensuales esperados del hogar: la entrada configurable del cálculo
 * de dinero disponible (ADR-0014).
 */
class ExpectedIncomeController extends Controller
{
    public function __construct(private readonly BudgetCalculatorService $calculator) {}

    public function index(Request $request): View|RedirectResponse
    {
        $household = active_household();

        if ($household === null) {
            return redirect()->route('households.create');
        }

        $this->authorize('viewAny', ExpectedIncome::class);

        return view('expected-incomes.index', [
            'expectedIncomes' => $household->expectedIncomes()
                ->with('category')
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->get(),
            'monthlyTotal' => $this->calculator->monthlyExpectedIncome($household->id),
            'categories' => Category::forHousehold($household->id)
                ->where('type', CategoryType::Income->value)
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(StoreExpectedIncomeRequest $request): RedirectResponse
    {
        $this->authorize('create', ExpectedIncome::class);

        active_household()->expectedIncomes()->create($request->validatedData());

        return redirect()
            ->route('expected-incomes.index')
            ->with('status', __('Ingreso esperado añadido.'));
    }

    public function update(UpdateExpectedIncomeRequest $request, ExpectedIncome $expectedIncome): RedirectResponse
    {
        $this->authorize('update', $expectedIncome);

        $expectedIncome->update($request->validatedData());

        return redirect()
            ->route('expected-incomes.index')
            ->with('status', __('Ingreso esperado actualizado.'));
    }

    public function destroy(Request $request, ExpectedIncome $expectedIncome): RedirectResponse
    {
        $this->authorize('delete', $expectedIncome);

        $expectedIncome->delete();

        return redirect()
            ->route('expected-incomes.index')
            ->with('status', __('Ingreso esperado ":name" eliminado.', ['name' => $expectedIncome->name]));
    }
}
