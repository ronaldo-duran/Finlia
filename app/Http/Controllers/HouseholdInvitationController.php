<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Household\StoreHouseholdInvitationRequest;
use App\Models\Household;
use App\Models\HouseholdInvitation;
use App\Services\HouseholdService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

/**
 * Envío y revocación de invitaciones (acciones del owner dentro de un hogar).
 */
class HouseholdInvitationController extends Controller
{
    public function __construct(private readonly HouseholdService $service) {}

    /**
     * Genera una invitación, se la envía por correo al invitado y devuelve al
     * owner el enlace por si el correo no salió (ADR-0015).
     */
    public function store(StoreHouseholdInvitationRequest $request, Household $household): RedirectResponse
    {
        $this->authorize('invite', $household);

        try {
            [$invitation, $plainToken, $emailSent] = $this->service->inviteMember(
                $household,
                $request->invitedEmail(),
                $request->invitedRole(),
                $request->user()?->name,
            );
        } catch (ValidationException $e) {
            return redirect()
                ->route('households.show', $household)
                ->withErrors($e->errors())
                ->withInput();
        }

        return redirect()
            ->route('households.show', $household)
            ->with('invitation_link', route('invitations.show', $plainToken))
            ->with('invitation_email', $invitation->email)
            ->with('invitation_email_sent', $emailSent);
    }

    /**
     * Revoca una invitación pendiente.
     */
    public function destroy(Household $household, HouseholdInvitation $invitation): RedirectResponse
    {
        $this->authorize('delete', $invitation);

        $this->service->revokeInvitation($invitation);

        return redirect()
            ->route('households.show', $household)
            ->with('status', __('Invitación revocada.'));
    }
}
