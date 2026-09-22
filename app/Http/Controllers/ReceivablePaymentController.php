<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Receivable\StoreReceivablePaymentRequest;
use App\Models\Receivable;
use App\Models\ReceivablePayment;
use App\Services\ReceivableService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Historial de cobros de una cuenta por cobrar (Épica 15).
 */
class ReceivablePaymentController extends Controller
{
    public function __construct(private readonly ReceivableService $receivables) {}

    /**
     * Registra un cobro. Si es tipo "recibido" y lleva cuenta, genera
     * además el ingreso real (ADR-0021 espejo) para que el saldo de esa
     * cuenta no mienta.
     */
    public function store(StoreReceivablePaymentRequest $request, Receivable $receivable): RedirectResponse
    {
        $this->authorize('collect', $receivable);

        $this->receivables->registerPayment($receivable, $request->validatedData(), $request->user());

        $receivable->refresh();

        $status = $request->filled('account_id') && $request->input('type') === 'received'
            ? __('Cobro registrado. Saldo pendiente: :balance.', ['balance' => money($receivable->current_balance)])
            : __('Cobro registrado (sin movimiento asociado). Saldo pendiente: :balance.', ['balance' => money($receivable->current_balance)]);

        return redirect()
            ->route('receivables.show', $receivable)
            ->with('status', $status);
    }

    /**
     * Borra un cobro y deshace su efecto (ingreso incluido).
     */
    public function destroy(Request $request, Receivable $receivable, ReceivablePayment $payment): RedirectResponse
    {
        $this->authorize('delete', $payment);

        abort_if($payment->receivable_id !== $receivable->id, 404);

        $this->receivables->deletePayment($payment);

        $receivable->refresh();

        return redirect()
            ->route('receivables.show', $receivable)
            ->with('status', __('Cobro eliminado. Saldo pendiente: :balance.', ['balance' => money($receivable->current_balance)]));
    }
}
