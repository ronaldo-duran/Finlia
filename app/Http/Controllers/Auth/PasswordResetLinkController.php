<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\StorePasswordResetLinkRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    /**
     * Muestra el formulario para solicitar el enlace de recuperación.
     */
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Envía el enlace de restablecimiento al correo.
     * Devuelve siempre el mismo mensaje de éxito para no revelar si el
     * correo existe (mitiga enumeración de usuarios).
     */
    public function store(StorePasswordResetLinkRequest $request): RedirectResponse
    {
        $request->sendResetLink();

        return back()
            ->with('status', __('passwords.sent'))
            ->onlyInput('email');
    }
}
