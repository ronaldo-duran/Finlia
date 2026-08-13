<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\HouseholdService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Aceptación de una invitación mediante el enlace con token (ADR-0003).
 */
class InvitationController extends Controller
{
    public function __construct(private readonly HouseholdService $service) {}

    /**
     * Muestra el detalle de la invitación y el botón para aceptarla.
     */
    public function show(Request $request, string $token): View
    {
        $invitation = $this->service->findInvitationByPlainToken($token);

        abort_if($invitation === null, 404);

        $user = $request->user();

        return view('invitations.accept', [
            'invitation' => $invitation,
            'token' => $token,
            'emailMismatch' => $user !== null
                && strtolower((string) $invitation->email) !== strtolower((string) $user->email),
        ]);
    }

    /**
     * Procesa la aceptación: vincula al usuario al hogar y lo activa.
     */
    public function accept(Request $request, string $token): RedirectResponse
    {
        $invitation = $this->service->findInvitationByPlainToken($token);

        abort_if($invitation === null, 404);

        try {
            $household = $this->service->acceptInvitation($invitation, $request->user());
        } catch (ValidationException $e) {
            return redirect()
                ->route('invitations.show', $token)
                ->withErrors($e->errors());
        }

        session(['household_id' => $household->id]);
        app()->forgetInstance('finlia.active_household');

        return redirect()
            ->route('dashboard')
            ->with('status', __('¡Te uniste a ":name"!', ['name' => $household->name]));
    }
}
