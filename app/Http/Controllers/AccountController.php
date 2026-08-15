<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Account\StoreAccountRequest;
use App\Http\Requests\Account\UpdateAccountRequest;
use App\Models\Account;
use App\Services\AccountBalanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function __construct(private readonly AccountBalanceService $balances) {}

    /**
     * Lista las cuentas del hogar activo.
     */
    public function index(Request $request): View
    {
        $accounts = active_household()
            ->accounts()
            ->withCount(['incomes', 'expenses'])
            ->latest()
            ->get();

        return view('accounts.index', ['accounts' => $accounts]);
    }

    public function create(): View
    {
        return view('accounts.create');
    }

    public function store(StoreAccountRequest $request): RedirectResponse
    {
        $this->authorize('create', Account::class);

        $account = active_household()->accounts()->create($request->validatedData());

        // current_balance = initial_balance (sin movimientos aún). ADR-0012.
        $this->balances->recompute($account);

        return redirect()
            ->route('accounts.index')
            ->with('status', __('Cuenta ":name" creada.', ['name' => $account->name]));
    }

    public function show(Request $request, Account $account): View
    {
        $this->authorize('view', $account);

        $account->load(['incomes' => fn ($q) => $q->latest('date')->take(10), 'expenses' => fn ($q) => $q->latest('date')->take(10)]);

        return view('accounts.show', ['account' => $account]);
    }

    public function edit(Request $request, Account $account): View
    {
        $this->authorize('update', $account);

        return view('accounts.edit', ['account' => $account]);
    }

    public function update(UpdateAccountRequest $request, Account $account): RedirectResponse
    {
        $this->authorize('update', $account);

        $account->update($request->validatedData());

        // Si cambió initial_balance (o por seguridad), se recalcula el saldo.
        $this->balances->recompute($account->fresh());

        return redirect()
            ->route('accounts.index')
            ->with('status', __('Cuenta actualizada.'));
    }

    public function destroy(Request $request, Account $account): RedirectResponse
    {
        $this->authorize('delete', $account);

        // No se borra una cuenta con movimientos (los saldos dejarían de cuadrar).
        if ($account->incomes()->exists() || $account->expenses()->exists()) {
            return redirect()
                ->route('accounts.index')
                ->with('error', __('No se puede eliminar ":name": tiene movimientos asociados. Desactívala en su lugar.', ['name' => $account->name]));
        }

        $account->delete();

        return redirect()
            ->route('accounts.index')
            ->with('status', __('Cuenta ":name" eliminada.', ['name' => $account->name]));
    }
}
