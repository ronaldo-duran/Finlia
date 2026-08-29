<?php

declare(strict_types=1);

namespace App\Http\Requests\Debt;

use App\Enums\DebtStatus;
use App\Enums\DebtType;
use App\Enums\InterestRateType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Valida el alta de una deuda.
 *
 * No acepta household_id (sale del hogar activo) ni current_balance (es
 * derivado, ADR-0020). account_id se acota a cuentas del hogar activo
 * (aislamiento, ADR-0005).
 */
class StoreDebtRequest extends FormRequest
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
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'institution' => ['nullable', 'string', 'max:120'],
            'type' => ['required', Rule::enum(DebtType::class)],
            'original_amount' => ['required', 'numeric', 'min:0.01', 'max:9999999999999.99'],
            // % efectivo anual. 0 es válido (préstamo familiar sin intereses).
            'interest_rate' => ['nullable', 'numeric', 'min:0', 'max:999.999'],
            'interest_rate_type' => ['nullable', Rule::enum(InterestRateType::class)],
            'minimum_payment' => ['nullable', 'numeric', 'min:0', 'max:9999999999999.99'],
            'scheduled_payment' => ['nullable', 'numeric', 'min:0', 'max:9999999999999.99'],
            'due_day' => ['nullable', 'integer', 'between:1,31'],
            'start_date' => ['nullable', 'date', 'before:2100-01-01'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date', 'before:2100-01-01'],
            'status' => ['nullable', Rule::enum(DebtStatus::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
            'account_id' => [
                'nullable',
                Rule::exists('accounts', 'id')->where(fn ($q) => $q->where('household_id', active_household_id())),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Ponle un nombre a la deuda.',
            'type.required' => 'Selecciona el tipo de deuda.',
            'original_amount.required' => 'Indica el monto original de la deuda.',
            'original_amount.min' => 'El monto original debe ser mayor que cero.',
            'due_day.between' => 'El día de pago debe estar entre 1 y 31.',
            'end_date.after_or_equal' => 'La fecha de fin no puede ser anterior a la de inicio.',
            'interest_rate.max' => 'Revisa la tasa: parece demasiado alta.',
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

        $data['status'] ??= DebtStatus::Active->value;

        return $data;
    }
}
