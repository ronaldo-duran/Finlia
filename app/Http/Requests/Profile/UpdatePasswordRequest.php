<?php

declare(strict_types=1);

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Cambio de contraseña con re-autenticación (Plan 02): la contraseña
 * actual es obligatoria — probar la identidad antes de rotar la llave.
 * La regla current_password valida contra el hash del usuario del guard.
 */
class UpdatePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // /perfil solo opera sobre el usuario autenticado (UserPolicy en el controlador)
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string', 'current_password:web'],
            // Política central de contraseñas (AppServiceProvider): misma para
            // registro, cambio y reset.
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ];
    }

    /**
     * Mensajes claros para la re-autenticación (el genérico de
     * current_password es críptico para quien no sabe de "guards").
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'current_password.current_password' => __('La contraseña actual no coincide.'),
        ];
    }
}
