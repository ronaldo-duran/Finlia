<?php

declare(strict_types=1);

namespace App\Http\Requests\Expense;

use App\Enums\CategoryType;
use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Valida el registro de un gasto.
 * No acepta household_id ni user_id (hogar activo + usuario autenticado).
 * account_id y category_id se acotan al hogar activo (aislamiento, ADR-0005).
 */
class StoreExpenseRequest extends FormRequest
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
            'account_id' => [
                'required',
                Rule::exists('accounts', 'id')->where(fn ($q) => $q
                    ->where('household_id', $householdId)
                    ->where('is_active', true)),
            ],
            'category_id' => [
                'nullable',
                Rule::exists('categories', 'id')->where(fn ($q) => $q
                    ->where('type', CategoryType::Expense->value)
                    ->where(fn ($q2) => $q2->whereNull('household_id')->orWhere('household_id', $householdId))),
            ],
            'date' => ['required', 'date', 'before_or_equal:today'],
            'description' => ['nullable', 'string', 'max:200'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'payment_method' => ['nullable', 'string', Rule::in(array_column(PaymentMethod::cases(), 'value'))],
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
