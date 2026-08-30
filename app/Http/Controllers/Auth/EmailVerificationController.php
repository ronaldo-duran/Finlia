<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Verificación de correo del registro (Plan 01, ADR-0029).
 *
 * Tres acciones: el aviso "revisa tu correo" (única pantalla alcanzable sin
 * verificar), el enlace firmado del correo y el reenvío. El enlace es
 * PÚBLICO + firmado: la firma es la autorización (mismo patrón que la baja
 * del digest, Épica 9) y el click puede llegar desde un buzón sin sesión.
 */
class EmailVerificationController extends Controller
{
    /**
     * Pantalla de aviso: "Revisa tu correo" (accesible sin verificar).
     */
    public function notice(): View
    {
        return view('auth.verify-email');
    }

    /**
     * Confirma el correo desde el enlace firmado del correo.
     *
     * El middleware 'signed' ya validó la firma (y la expiración); aquí se
     * verifica que el hash corresponda al correo del usuario, que es la otra
     * mitad de la prueba de posesión del buzón.
     */
    public function verify(Request $request, int $id): RedirectResponse
    {
        $user = User::findOrFail($id);

        if (! hash_equals((string) sha1($user->getEmailForVerification()), (string) $request->route('hash'))) {
            abort(403, __('Este enlace de verificación no es válido.'));
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();

            event(new Verified($user));
        }

        // Regenera la sesión al autenticar la identidad (fijación de sesión).
        if ($request->user()?->is($user)) {
            $request->session()->regenerate();

            return redirect()
                ->intended(route('dashboard'))
                ->with('status', __('¡Correo confirmado! Bienvenido a Finlia.'));
        }

        // Click desde otro dispositivo/navegador: entra a iniciar sesión.
        return redirect()
            ->route('login')
            ->with('status', __('Correo confirmado. Ya puedes iniciar sesión.'));
    }

    /**
     * Reenvía el correo de verificación (throttle por usuario, ~3/min).
     */
    public function resend(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return redirect()->route('dashboard');
        }

        $user->sendEmailVerificationNotification();

        return back()->with('status', __('Reenviamos el enlace de confirmación a tu correo.'));
    }
}
