<?php

declare(strict_types=1);

namespace App\Http\Requests\Reminder;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida el interruptor de recordatorios del hogar (Épica 9: "permitir
 * activar/desactivar recordatorios"). Solo lo cambia el administrador
 * del hogar (HouseholdPolicy::update).
 */
class UpdateReminderSettingsRequest extends FormRequest
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
            'reminders_enabled' => ['required', 'boolean'],
        ];
    }

    public function validatedData(): array
    {
        return ['reminders_enabled' => $this->boolean('reminders_enabled')];
    }
}
