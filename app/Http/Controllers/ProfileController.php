<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Profile\UpdateEmailRequest;
use App\Http\Requests\Profile\UpdatePasswordRequest;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Models\User;
use App\Services\ProfileService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Perfil del usuario autenticado (Plan 02, ADR-0030): nombre, contraseña
 * y correo. Es preferencia del USUARIO, no del hogar — vive fuera del
 * multi-tenant de finanzas. Toda acción pasa por UserPolicy (solo uno
 * mismo) y la lógica vive en ProfileService.
 */
class ProfileController extends Controller
{
    public function __construct(private readonly ProfileService $service) {}

    /**
     * Pantalla /perfil: datos, contraseña y correo (con su pendiente).
     */
    public function edit(Request $request): View
    {
        $this->authorize('update', $request->user());

        /** @var User $user */
        $user = $request->user();

        return view('profile.edit', [
            'user' => $user,
            'pendingExpiresAt' => $user->pending_email_requested_at
                ?->copy()
                ->addMinutes(ProfileService::EMAIL_CHANGE_TTL_MINUTES),
        ]);
    }

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $user = $request->user();
        $this->authorize('update', $user);

        $user->update($request->validated());

        return back()->with('status', __('Datos actualizados.'));
    }

    /**
     * Cambia la contraseña y revoca las demás sesiones.
     */
    public function updatePassword(UpdatePasswordRequest $request): RedirectResponse
    {
        $user = $request->user();
        $this->authorize('update', $user);

        $password = (string) $request->validated('password');

        $this->service->changePassword($user, $password);

        // Revoca las demás sesiones y las cookies de "recuérdame":
        // logoutOtherDevices re-hashea la contraseña y AuthenticateSession
        // compara ese hash en cada sesión (ADR-0030). La sesión actual
        // sobrevive: no hay que volver a iniciar sesión.
        Auth::logoutOtherDevices($password);

        return back()->with('status', __('Contraseña actualizada. Cerramos las demás sesiones de tu cuenta.'));
    }

    /**
     * Arranca el cambio de correo (queda pendiente hasta confirmar).
     */
    public function updateEmail(UpdateEmailRequest $request): RedirectResponse
    {
        $user = $request->user();
        $this->authorize('update', $user);

        $this->service->requestEmailChange($user, (string) $request->validated('email'));

        return back()->with('status', __('Enviamos un enlace de confirmación a tu correo nuevo. Tienes una hora para confirmarlo desde esa bandeja.'));
    }

    /**
     * Confirma el cambio desde el enlace del correo nuevo (ruta pública:
     * el click llega desde la bandeja, sin sesión).
     *
     * Los fallos (token inválido/expirado/conflicto) muestran una pantalla
     * explicativa — un redirect "atrás" caería en el cliente de correo.
     */
    public function confirmEmail(Request $request, string $token): View|RedirectResponse
    {
        try {
            $user = $this->service->confirmEmailChange($token);
        } catch (ValidationException $e) {
            return view('profile.email-error', [
                'message' => $e->validator->errors()->first(),
            ]);
        }

        if ($request->user()?->is($user)) {
            return redirect()
                ->route('profile.edit')
                ->with('status', __('Listo: tu correo ahora es :email.', ['email' => $user->email]));
        }

        return redirect()
            ->route('login')
            ->with('status', __('Correo confirmado. Ya puedes iniciar sesión.'));
    }
}
