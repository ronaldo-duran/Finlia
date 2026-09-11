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
     * Muestra la invitación. Es pública (ADR-0039): el enlace llega desde un
     * buzón o un WhatsApp, casi siempre sin sesión, y el token es la
     * autorización. Aceptar sí exige sesión verificada.
     */
    public function show(Request $request, string $token): View
    {
        $invitation = $this->service->findInvitationByPlainToken($token);

        abort_if($invitation === null, 404);

        $user = $request->user();
        $acceptable = $invitation->isPending() && ! $invitation->isExpired();

        // Sin sesión, la invitación se recuerda para el paso siguiente: el
        // login vuelve aquí (url.intended) y el registro fija el correo y no
        // crea hogar propio. En sesión y no en la URL, para no multiplicar el
        // token por historiales y cabeceras Referer.
        if ($user === null && $acceptable) {
            $request->session()->put('invitation_token', $token);
            $request->session()->put('url.intended', route('invitations.show', $token));
        }

        return view('invitations.accept', [
            'invitation' => $invitation,
            'token' => $token,
            'acceptable' => $acceptable,
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

        $request->session()->forget('invitation_token');
        session(['household_id' => $household->id]);
        app()->forgetInstance('finlia.active_household');

        return redirect()
            ->route('dashboard')
            ->with('status', __('¡Te uniste a ":name"!', ['name' => $household->name]));
    }
}
