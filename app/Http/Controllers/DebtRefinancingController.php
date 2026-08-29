<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Debt\StoreDebtRefinancingRequest;
use App\Models\Debt;
use App\Services\DebtService;
use Illuminate\Http\RedirectResponse;

/**
 * Refinanciación de una deuda (Épica 6): nuevas condiciones y nueva línea
 * base del saldo (ADR-0020).
 */
class DebtRefinancingController extends Controller
{
    public function __construct(private readonly DebtService $debts) {}

    public function store(StoreDebtRefinancingRequest $request, Debt $debt): RedirectResponse
    {
        $this->authorize('refinance', $debt);

        $this->debts->registerRefinancing($debt, $request->validatedData());

        return redirect()
            ->route('debts.show', $debt)
            ->with('status', __('Refinanciación registrada. El saldo parte ahora del nuevo monto.'));
    }
}
