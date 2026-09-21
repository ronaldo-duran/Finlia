<?php

declare(strict_types=1);

namespace App\Http\Requests\Receivable;

use App\Enums\CategoryType;
use App\Enums\ReceivablePaymentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Valida el registro de un cobro contra una cuenta por cobrar.
 *
 * `account_id` es opcional: si se indica y el tipo es "cobro recibido",
 * el cobro genera además el ingreso real que sube el saldo de esa
 * cuenta (ADR-0021 espejo). Se acota al hogar activo, igual que la
 * categoría (aislamiento multi-hogar).
 */
class StoreReceivablePaymentRequest extends FormRequest
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
            'amount' => ['required', 'numeric', 'min:0.01', 'max:9999999999999.99'],
            'date' => ['required', 'date', 'before_or_equal:today'],
            'type' => ['required', Rule::enum(ReceivablePaymentType::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
            'account_id' => [
                'nullable',
                Rule::exists('accounts', 'id')->where(fn ($q) => $q->where('household_id', $householdId)),
            ],
            'category_id' => [
                'nullable',
                Rule::exists('categories', 'id')->where(fn ($q) => $q
                    ->where('type', CategoryType::Income->value)
                    ->where(fn ($q2) => $q2->whereNull('household_id')->orWhere('household_id', $householdId))),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.required' => 'Indica cuánto te pagaron.',
            'amount.min' => 'El cobro debe ser mayor que cero.',
            'date.required' => 'Indica la fecha del cobro.',
            'date.before_or_equal' => 'No se pueden registrar cobros con fecha futura.',
            'type.required' => 'Indica si es un cobro, una condonación o un ajuste.',
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
