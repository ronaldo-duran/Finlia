<?php

declare(strict_types=1);

namespace App\Http\Requests\SavingsGoal;

use App\Enums\SavingsGoalPriority;
use App\Enums\SavingsGoalStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Valida el alta de una meta de ahorro.
 *
 * No acepta household_id (sale del hogar activo) ni current_amount (es
 * derivado de los aportes, ADR-0025).
 */
class StoreSavingsGoalRequest extends FormRequest
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
            'target_amount' => ['required', 'numeric', 'min:0.01', 'max:9999999999999.99'],
            // Sin fecha objetivo la meta es abierta (típico del fondo de
            // emergencia): no hay recomendación de aporte, pero sí progreso.
            'target_date' => ['nullable', 'date', 'after:today'],
            'priority' => ['nullable', Rule::enum(SavingsGoalPriority::class)],
            'monthly_commitment' => ['nullable', 'numeric', 'min:0', 'max:9999999999999.99'],
            'is_emergency_fund' => ['nullable', 'boolean'],
            'status' => ['nullable', Rule::enum(SavingsGoalStatus::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Ponle un nombre a la meta.',
            'target_amount.required' => 'Indica cuánto quieres ahorrar.',
            'target_amount.min' => 'El objetivo debe ser mayor que cero.',
            'target_date.after' => 'La fecha objetivo debe ser futura.',
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

        $data['status'] ??= SavingsGoalStatus::Active->value;
        $data['is_emergency_fund'] = (bool) ($data['is_emergency_fund'] ?? false);

        return $data;
    }
}
