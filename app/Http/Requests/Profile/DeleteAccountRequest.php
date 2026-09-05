<?php

declare(strict_types=1);

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Solicitud de eliminación de cuenta (Plan 05, ADR-0033).
 * Re-autenticación obligatoria: el usuario confirma su identidad antes de
 * arrancar la suspensión. Misma regla current_password que el cambio de clave.
 */
class DeleteAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // La autorización es UserPolicy en el controlador.
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string', 'current_password:web'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'current_password.current_password' => __('La contraseña no coincide. Inténtalo de nuevo.'),
        ];
    }
}
