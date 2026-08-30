<?php

declare(strict_types=1);

namespace App\Http\Requests\Reminder;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida la preferencia personal de digest por correo (Épica 9, ADR-0028).
 * Es del miembro, no del hogar: cada quien decide si quiere el aviso.
 * Opt-out implícito en el alta: el campo nace en false (jamás marketing).
 */
class UpdateReminderEmailRequest extends FormRequest
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
            'reminders_email' => ['required', 'boolean'],
        ];
    }

    public function validatedData(): array
    {
        return ['reminders_email' => $this->boolean('reminders_email')];
    }
}
