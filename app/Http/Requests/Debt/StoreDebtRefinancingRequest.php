<?php

declare(strict_types=1);

namespace App\Http\Requests\Debt;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida el registro de una refinanciación: fija la nueva línea base del
 * saldo y las nuevas condiciones (ADR-0020).
 */
class StoreDebtRefinancingRequest extends FormRequest
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
            'refinanced_balance' => ['required', 'numeric', 'min:0.01', 'max:9999999999999.99'],
            'interest_rate' => ['nullable', 'numeric', 'min:0', 'max:999.999'],
            'term_months' => ['nullable', 'integer', 'between:1,600'],
            'installment' => ['nullable', 'numeric', 'min:0', 'max:9999999999999.99'],
            'start_date' => ['required', 'date', 'before:2100-01-01'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'refinanced_balance.required' => 'Indica el saldo que queda refinanciado.',
            'refinanced_balance.min' => 'El saldo refinanciado debe ser mayor que cero.',
            'start_date.required' => 'Indica desde cuándo aplican las nuevas condiciones.',
            'term_months.between' => 'El plazo debe estar entre 1 y 600 meses.',
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
