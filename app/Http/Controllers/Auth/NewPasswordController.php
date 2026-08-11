<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\StoreNewPasswordRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class NewPasswordController extends Controller
{
    /**
     * Muestra el formulario para definir una nueva contraseña.
     */
    public function create(Request $request): View
    {
        return view('auth.reset-password', [
            'token' => $request->route('token'),
            'email' => $request->string('email')->toString(),
        ]);
    }

    /**
     * Restablece la contraseña usando el token.
     */
    public function store(StoreNewPasswordRequest $request): RedirectResponse
    {
        $request->resetPassword();

        return redirect()
            ->route('login')
            ->with('status', __(Password::PASSWORD_RESET));
    }
}
