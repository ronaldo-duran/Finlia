<?php

declare(strict_types=1);

namespace App\Http\Requests\Reminder;

use Illuminate\Validation\Rule;

/**
 * Valida la edición de un recordatorio suelto. Mismas reglas que el alta
 * (el estado no se edita a mano: se atiende con "Completar").
 */
class UpdateReminderRequest extends StoreReminderRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:2', 'max:120'],
            'amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999999.99'],
            'due_date' => ['required', 'date', 'before:2100-01-01'],
            'frequency' => ['nullable', Rule::in(self::repeatableFrequencies())],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
