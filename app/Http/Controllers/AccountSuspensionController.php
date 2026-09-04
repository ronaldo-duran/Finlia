<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\AccountDeletionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Página de cuenta suspendida y reactivación (Plan 05, ADR-0033).
 * Solo accesible desde el grupo auth (sin account.active ni terms.current).
 */
class AccountSuspensionController extends Controller
{
    public function __construct(private readonly AccountDeletionService $service) {}

    public function show(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if (! $user->isSuspended()) {
            return redirect()->route('dashboard');
        }

        return view('account.suspended', [
            'user' => $user,
            'deadline' => $user->deletionDeadline(),
        ]);
    }

    /**
     * Reactiva la cuenta limpiando deletion_requested_at.
     * El usuario ya está autenticado (llegó hasta aquí por login).
     */
    public function reactivate(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user->isSuspended()) {
            return redirect()->route('dashboard');
        }

        $this->service->cancelDeletion($user);

        return redirect()->route('dashboard')
            ->with('status', __('Tu cuenta está activa de nuevo. Bienvenido/a de vuelta.'));
    }
}
