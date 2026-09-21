<?php

declare(strict_types=1);

namespace App\Http\Requests\Receivable;

use App\Enums\ReceivableStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Valida el alta de una cuenta por cobrar.
 *
 * No acepta household_id (sale del hogar activo) ni current_balance (es
 * derivado). debtor_user_id se acota a miembros del hogar activo
 * (aislamiento, ADR-0005).
 */
class StoreReceivableRequest extends FormRequest
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
            'debtor_name' => ['required', 'string', 'min:2', 'max:120'],
            'debtor_user_id' => [
                'nullable',
                Rule::exists('household_user', 'user_id')
                    ->where(fn ($q) => $q->where('household_id', $householdId)),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'original_amount' => ['required', 'numeric', 'min:0.01', 'max:9999999999999.99'],
            'currency' => ['nullable', 'string', 'size:3'],
            'status' => ['nullable', Rule::enum(ReceivableStatus::class)],
            'due_date' => ['nullable', 'date', 'before:2100-01-01'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Ponle un nombre a la cuenta por cobrar.',
            'debtor_name.required' => '¿Quién debe? Escribe su nombre.',
            'original_amount.required' => 'Indica cuánto te deben.',
            'original_amount.min' => 'El monto debe ser mayor que cero.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function validatedData(): array
    {
        $data = array_map(
            static fn ($value) => is_string($value) ? trim($value) : $value,
            $this->validated(),
        );

        $data['status'] ??= ReceivableStatus::Pending->value;
        $data['currency'] ??= 'COP';

        return $data;
    }
}
