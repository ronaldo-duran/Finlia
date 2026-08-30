<?php

declare(strict_types=1);

namespace App\Http\Requests\RecurringExpense;

use App\Enums\CategoryType;
use App\Enums\Frequency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Valida el alta de un gasto recurrente u obligación futura.
 * No acepta household_id: se toma del hogar activo. category_id se acota a
 * categorías de gasto del hogar y account_id a cuentas del hogar
 * (aislamiento, ADR-0005).
 */
class StoreRecurringExpenseRequest extends FormRequest
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
            'frequency' => ['required', Rule::enum(Frequency::class)],
            // Obligatorio solo para frecuencia personalizada: cada N días (1-10 años).
            'frequency_interval' => [
                Rule::requiredIf($this->input('frequency') === Frequency::Custom->value),
                'nullable', 'integer', 'between:1,3650',
            ],
            // Puede ser pasada: así se representa una obligación ya vencida.
            'next_date' => ['required', 'date', 'before:2100-01-01'],
            'category_id' => [
                'nullable',
                Rule::exists('categories', 'id')->where(fn ($q) => $q
                    ->where('type', CategoryType::Expense->value)
                    ->where(fn ($q2) => $q2->whereNull('household_id')->orWhere('household_id', $householdId))),
            ],
            'account_id' => [
                'nullable',
                Rule::exists('accounts', 'id')->where(fn ($q) => $q->where('household_id', $householdId)),
            ],
            'is_active' => ['boolean'],
            // Épica 9 (ADR-0018): opt-in por obligación al pago automático.
            'auto_generate' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.min' => 'El monto estimado debe ser mayor que cero.',
            'frequency.required' => 'Selecciona la frecuencia del gasto.',
            'frequency_interval.required_if' => 'Indica cada cuántos días se repite (frecuencia personalizada).',
            'frequency_interval.between' => 'El intervalo debe estar entre 1 y 3650 días.',
            'next_date.required' => 'Indica la próxima fecha de pago.',
        ];
    }

    /**
     * Normaliza el checkbox y descarta el intervalo cuando la frecuencia
     * no es personalizada (evita datos huérfanos).
     */
    protected function prepareForValidation(): void
    {
        $data = [
            'is_active' => $this->boolean('is_active'),
            'auto_generate' => $this->boolean('auto_generate'),
        ];

        if ($this->input('frequency') !== Frequency::Custom->value) {
            $data['frequency_interval'] = null;
        }

        $this->merge($data);
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
