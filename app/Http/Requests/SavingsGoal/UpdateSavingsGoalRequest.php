<?php

declare(strict_types=1);

namespace App\Http\Requests\SavingsGoal;

use App\Enums\SavingsGoalPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Valida la edición de una meta de ahorro.
 *
 * El estado NO se edita aquí: pausar, completar y archivar son acciones
 * dedicadas (POST), porque son decisiones que el usuario toma de forma
 * puntual, no campos que se rellenan.
 */
class UpdateSavingsGoalRequest extends FormRequest
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
            // En edición la fecha puede quedar en el pasado (el tiempo pasa);
            // solo se pide que sea una fecha válida.
            'target_date' => ['nullable', 'date', 'before:2100-01-01'],
            'priority' => ['nullable', Rule::enum(SavingsGoalPriority::class)],
            'monthly_commitment' => ['nullable', 'numeric', 'min:0', 'max:9999999999999.99'],
            'is_emergency_fund' => ['nullable', 'boolean'],
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

        $data['is_emergency_fund'] = (bool) ($data['is_emergency_fund'] ?? false);

        return $data;
    }
}
