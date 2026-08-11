<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

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
            'email' => ['required', 'string', 'email:rfc', 'max:150', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'max:72', 'confirmed'],
        ];
    }
}
