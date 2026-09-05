<?php

declare(strict_types=1);

namespace App\Http\Requests\Transfer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Valida la edición de una transferencia entre cuentas (Épica 10, ADR-0035).
 */
class UpdateTransferRequest extends FormRequest
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

        $activeAccount = Rule::exists('accounts', 'id')->where(
            fn ($q) => $q->where('household_id', $householdId)->where('is_active', true)
        );

        return [
            'from_account_id' => ['required', $activeAccount],
            'to_account_id' => ['required', $activeAccount],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:9999999999999.99'],
            'date' => ['required', 'date', 'before_or_equal:today'],
            'description' => ['nullable', 'string', 'max:200'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isEmpty()
                    && $this->integer('from_account_id') === $this->integer('to_account_id')) {
                    $validator->errors()->add('to_account_id', 'La cuenta de destino debe ser diferente a la de origen.');
                }
            },
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
