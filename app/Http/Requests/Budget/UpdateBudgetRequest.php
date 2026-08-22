<?php

declare(strict_types=1);

namespace App\Http\Requests\Budget;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida la edición de un presupuesto.
 *
 * Solo el monto es editable: cambiar la categoría o el mes equivale a otro
 * presupuesto distinto (se crea uno nuevo y se borra el anterior). Así se
 * evita además tener que revalidar la unicidad hogar/categoría/mes.
 */
class UpdateBudgetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01', 'max:9999999999999.99'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.min' => 'El monto del presupuesto debe ser mayor que cero.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function validatedData(): array
    {
        return $this->validated();
    }
}
