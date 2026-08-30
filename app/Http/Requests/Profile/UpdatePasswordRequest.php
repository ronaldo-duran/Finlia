<?php

declare(strict_types=1);

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;

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
            // Mismas reglas que el registro (StoreRegistrationRequest).
            'password' => ['required', 'string', 'min:8', 'max:72', 'confirmed'],
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
