<?php

declare(strict_types=1);

namespace App\Http\Requests\Budget;

use App\Models\Budget;
use Illuminate\Contracts\Validation\Validator;
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
            'envelope' => ['nullable', 'boolean'],
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
     * Los sobres solo aplican a presupuestos por categoría. El presupuesto
     * total del mes (sin categoría) nunca puede marcarse como sobre.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var Budget|null $budget */
            $budget = $this->route('budget');

            if ($this->boolean('envelope') && $budget !== null && $budget->isTotal()) {
                $validator->errors()->add('envelope', 'El presupuesto total del mes no puede marcarse como sobre. Los sobres son por categoría.');
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function validatedData(): array
    {
        $data = $this->validated();
        $data['envelope'] = $this->boolean('envelope');

        return $data;
    }
}
