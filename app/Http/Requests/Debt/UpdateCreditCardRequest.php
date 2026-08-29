<?php

declare(strict_types=1);

namespace App\Http\Requests\Debt;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida los atributos de una tarjeta de crédito (ADR-0002).
 *
 * ⚠️ No acepta —ni debe aceptar nunca— número de tarjeta, CVV ni PIN
 * (docs/SECURITY.md §4). Cualquier campo de ese tipo que llegue en la
 * petición se descarta por no estar en las reglas.
 */
class UpdateCreditCardRequest extends FormRequest
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
            'credit_limit' => ['required', 'numeric', 'min:0.01', 'max:9999999999999.99'],
            'statement_date' => ['nullable', 'integer', 'between:1,31'],
            'payment_due_date' => ['nullable', 'integer', 'between:1,31'],
            'annual_fee' => ['nullable', 'numeric', 'min:0', 'max:9999999999999.99'],
            'monthly_fee' => ['nullable', 'numeric', 'min:0', 'max:9999999999999.99'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'credit_limit.required' => 'Indica el cupo total de la tarjeta.',
            'credit_limit.min' => 'El cupo debe ser mayor que cero.',
            'statement_date.between' => 'El día de corte debe estar entre 1 y 31.',
            'payment_due_date.between' => 'El día límite de pago debe estar entre 1 y 31.',
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
