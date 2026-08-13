<?php

declare(strict_types=1);

namespace App\Http\Requests\Household;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Valida la creación de un hogar.
 * No acepta owner_id: se asigna desde el usuario autenticado en el controlador.
 */
class StoreHouseholdRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // cualquier usuario autenticado puede crear un hogar
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'currency' => ['required', 'string', 'size:3'],
            'timezone' => ['required', 'string', 'max:64', Rule::in(\DateTimeZone::listIdentifiers())],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function validatedData(): array
    {
        return array_map(
            static fn ($value) => is_string($value) ? trim($value) : $value,
            $this->validated(),
        );
    }
}
