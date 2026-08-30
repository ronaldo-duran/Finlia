<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Valida el alta de un usuario (registro).
 * No acepta campos ajenos al alta (p. ej. roles o household_id).
 */
class StoreRegistrationRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:120'],
            // Un correo solo cuenta como tomado si está VERIFICADO. Un
            // registro sin verificar es un fantasma que el propio registro
            // reclama (anti-squatting, Plan 01): el dueño real del correo
            // nunca ve "ya está registrado".
            'email' => [
                'required',
                'string',
                'email:rfc',
                'max:150',
                Rule::unique('users', 'email')->whereNotNull('email_verified_at'),
            ],
            'password' => ['required', 'string', 'min:8', 'max:72', 'confirmed'],
        ];
    }
}
