<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Password;

/**
 * Valida la solicitud de enlace para restablecer contraseña.
 */
class StorePasswordResetLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // ruta pública bajo middleware 'guest'
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email:rfc', 'max:150'],
        ];
    }

    /**
     * Envía el enlace de restablecimiento.
     * No revela si el email existe (previene enumeración de usuarios).
     *
     * @return string Estado del broker (PASSWORD_RESET_LINK_SENT, etc.)
     */
    public function sendResetLink(): string
    {
        return Password::sendResetLink(
            $this->only('email')
        );
    }
}
