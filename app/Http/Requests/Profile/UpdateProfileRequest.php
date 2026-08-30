<?php

declare(strict_types=1);

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Datos básicos del perfil (Plan 02): solo el nombre. Fecha de nacimiento,
 * región y género llegan con el plan 04 — no aceptarlos antes.
 */
class UpdateProfileRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:120'],
        ];
    }
}
