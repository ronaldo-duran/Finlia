<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Debt\StoreDebtPaymentRequest;
use App\Models\Debt;
use App\Models\DebtPayment;
use App\Services\DebtService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Historial de pagos de una deuda (Épica 6).
 */
class DebtPaymentController extends Controller
{
    public function __construct(private readonly DebtService $debts) {}

    /**
     * Registra un pago. Si lleva cuenta, genera además el gasto real
     * (ADR-0021) para que el saldo de esa cuenta no mienta.
     */
    public function store(StoreDebtPaymentRequest $request, Debt $debt): RedirectResponse
    {
        $this->authorize('pay', $debt);

        $this->debts->registerPayment($debt, $request->validatedData(), $request->user());

        $debt->refresh();

        $status = $request->filled('account_id')
            ? __('Pago registrado. Nuevo saldo: :balance.', ['balance' => money($debt->current_balance)])
            : __('Pago registrado (sin cuenta asociada, no se creó movimiento). Nuevo saldo: :balance.', ['balance' => money($debt->current_balance)]);

        return redirect()
            ->route('debts.show', $debt)
            ->with('status', $status);
    }

    /**
     * Borra un pago y deshace su efecto (gasto incluido).
     */
    public function destroy(Request $request, Debt $debt, DebtPayment $payment): RedirectResponse
    {
        $this->authorize('delete', $payment);

        // Defensivo: el pago tiene que ser de esta deuda, no de otra del hogar.
        abort_if($payment->debt_id !== $debt->id, 404);

        $this->debts->deletePayment($payment);

        $debt->refresh();

        return redirect()
            ->route('debts.show', $debt)
            ->with('status', __('Pago eliminado. Nuevo saldo: :balance.', ['balance' => money($debt->current_balance)]));
    }
}
