<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Transfer\StoreTransferRequest;
use App\Http\Requests\Transfer\UpdateTransferRequest;
use App\Models\Account;
use App\Models\Transfer;
use App\Services\MovementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Transferencias entre cuentas del hogar (Épica 10, ADR-0035).
 *
 * Controlador fino: toda la lógica financiera y el recomputo de saldos
 * viven en MovementService (ADR-0010).
 */
class TransferController extends Controller
{
    public function __construct(private readonly MovementService $movements) {}

    public function create(): View
    {
        return view('transfers.create', $this->formOptions());
    }

    public function store(StoreTransferRequest $request): RedirectResponse
    {
        $this->authorize('create', Transfer::class);

        $this->movements->createTransfer(
            $request->validatedData(),
            active_household(),
            $request->user(),
        );

        return redirect()
            ->route('movements.index')
            ->with('status', __('Transferencia registrada.'));
    }

    public function edit(Request $request, Transfer $transfer): View
    {
        $this->authorize('update', $transfer);

        return view('transfers.edit', array_merge(['transfer' => $transfer], $this->formOptions()));
    }

    public function update(UpdateTransferRequest $request, Transfer $transfer): RedirectResponse
    {
        $this->authorize('update', $transfer);

        $this->movements->updateTransfer($transfer, $request->validatedData());

        return redirect()
            ->route('movements.index')
            ->with('status', __('Transferencia actualizada.'));
    }

    public function destroy(Request $request, Transfer $transfer): RedirectResponse
    {
        $this->authorize('delete', $transfer);

        $this->movements->deleteTransfer($transfer);

        return redirect()
            ->route('movements.index')
            ->with('status', __('Transferencia eliminada.'));
    }

    /**
     * Listas de cuentas activas del hogar para los selects del formulario.
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
        ];
    }
}
