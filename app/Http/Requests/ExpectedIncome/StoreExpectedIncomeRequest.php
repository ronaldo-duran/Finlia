<?php

declare(strict_types=1);

namespace App\Http\Requests\ExpectedIncome;

use App\Enums\CategoryType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Valida el alta de un ingreso mensual esperado (salario, arriendo…).
 * No acepta household_id: se toma del hogar activo. category_id se acota a
 * categorías de ingreso visibles por el hogar (aislamiento, ADR-0005).
 */
class StoreExpectedIncomeRequest extends FormRequest
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
        $householdId = active_household_id();

        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:9999999999999.99'],
            'category_id' => [
                'nullable',
                Rule::exists('categories', 'id')->where(fn ($q) => $q
                    ->where('type', CategoryType::Income->value)
                    ->where(fn ($q2) => $q2->whereNull('household_id')->orWhere('household_id', $householdId))),
            ],
            'day_of_month' => ['nullable', 'integer', 'between:1,31'],
            'is_active' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.min' => 'El monto esperado debe ser mayor que cero.',
            'day_of_month.between' => 'El día de cobro debe estar entre 1 y 31.',
        ];
    }

    /**
     * Los checkbox sin marcar no se envían: se normaliza a booleano.
     */
    protected function prepareForValidation(): void
    {
        $this->merge(['is_active' => $this->boolean('is_active')]);
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
