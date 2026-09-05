<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Profile\DeleteAccountRequest;
use App\Http\Requests\Profile\UpdateEmailRequest;
use App\Http\Requests\Profile\UpdatePasswordRequest;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Models\User;
use App\Services\AccountDeletionService;
use App\Services\DataExportService;
use App\Services\ProfileService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Perfil del usuario autenticado (Plan 02, ADR-0030): nombre, contraseña
 * y correo. Es preferencia del USUARIO, no del hogar — vive fuera del
 * multi-tenant de finanzas. Toda acción pasa por UserPolicy (solo uno
 * mismo) y la lógica vive en ProfileService.
 */
class ProfileController extends Controller
{
    public function __construct(
        private readonly ProfileService $service,
        private readonly AccountDeletionService $deletionService,
        private readonly DataExportService $exportService,
    ) {}

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
     * Solicita la eliminación de la cuenta (Plan 05, ADR-0033).
     * Verifica la contraseña actual, marca la suspensión y cierra sesión.
     */
    public function requestDeletion(DeleteAccountRequest $request): RedirectResponse
    {
        $user = $request->user();
        $this->authorize('update', $user);

        $this->deletionService->requestDeletion($user);

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')
            ->with('status', __('Tu cuenta se suspendió. Tienes 30 días para reactivarla iniciando sesión.'));
    }

    /**
     * Exporta todos los datos del hogar activo en un ZIP (Plan 06, ADR-0034).
     * Acotado al hogar activo; nunca datos de otro hogar ni de otros miembros.
     */
    public function export(Request $request): BinaryFileResponse
    {
        $user = $request->user();
        $this->authorize('update', $user);

        $household = active_household();

        abort_if($household === null, 404, __('No tienes un hogar activo para exportar.'));

        $zipPath = $this->exportService->buildZip($household, $user);

        $filename = 'finlia-'.$household->id.'-'.now()->format('Ymd').'.zip';

        $response = response()->download($zipPath, $filename, [
            'Content-Type' => 'application/zip',
        ])->deleteFileAfterSend();

        return $response;
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
