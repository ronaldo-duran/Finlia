<?php

declare(strict_types=1);

namespace App\Http\Requests\Reminder;

use App\Enums\Frequency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Valida el alta de un recordatorio suelto (Épica 9): una obligación
 * puntual o anual que no vive en recurrentes/deudas/metas — tecnomecánica,
 * renovación de pasaporte…
 *
 * No acepta household_id: se toma del hogar activo. La repetición se acota
 * a mensual→anual: un recordatorio más frecuente que eso es un gasto
 * recurrente (Épica 5), no un aviso suelto.
 */
class StoreReminderRequest extends FormRequest
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
            'title' => ['required', 'string', 'min:2', 'max:120'],
            'amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999999.99'],
            // Puede ser pasada: así se representa una obligación ya vencida.
            'due_date' => ['required', 'date', 'before:2100-01-01'],
            'frequency' => ['nullable', Rule::in($this->repeatableFrequencies())],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Escribe de qué es el recordatorio.',
            'due_date.required' => 'Indica la fecha límite.',
            'frequency.in' => 'La repetición solo puede ser mensual, trimestral, semestral o anual.',
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

    /**
     * Frecuencias con sentido para un recordatorio suelto.
     *
     * @return list<string>
     */
    public static function repeatableFrequencies(): array
    {
        return [
            Frequency::Monthly->value,
            Frequency::Quarterly->value,
            Frequency::Semester->value,
            Frequency::Yearly->value,
        ];
    }
}
