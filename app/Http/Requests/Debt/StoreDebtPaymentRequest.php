<?php

declare(strict_types=1);

namespace App\Http\Requests\Debt;

use App\Enums\CategoryType;
use App\Enums\DebtPaymentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Valida el registro de un pago contra una deuda.
 *
 * `account_id` es opcional: si se indica, el pago genera además el gasto
 * real que mueve el saldo de esa cuenta (ADR-0021). Se acota al hogar
 * activo, igual que la categoría.
 */
class StoreDebtPaymentRequest extends FormRequest
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
            'type' => ['required', Rule::enum(DebtPaymentType::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
            'account_id' => [
                'nullable',
                Rule::exists('accounts', 'id')->where(fn ($q) => $q->where('household_id', $householdId)),
            ],
            'category_id' => [
                'nullable',
                Rule::exists('categories', 'id')->where(fn ($q) => $q
                    ->where('type', CategoryType::Expense->value)
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
            'amount.required' => 'Indica cuánto pagaste.',
            'amount.min' => 'El pago debe ser mayor que cero.',
            'date.required' => 'Indica la fecha del pago.',
            'date.before_or_equal' => 'No se pueden registrar pagos con fecha futura.',
            'type.required' => 'Indica si fue pago mínimo, cuota pactada o abono extra.',
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
