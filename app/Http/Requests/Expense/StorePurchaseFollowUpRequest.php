<?php

declare(strict_types=1);

namespace App\Http\Requests\Expense;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Valida la respuesta al seguimiento de una compra (Épica 12).
 *
 * `mood_after` obligatorio (misma escala 1..5 del árbol original), `regret`
 * y `note` opcionales. La autorización (mismo autor, mismo hogar) la
 * resuelve el controlador con `abort_unless`.
 */
class StorePurchaseFollowUpRequest extends FormRequest
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
            'mood_after' => ['required', 'integer', Rule::in([1, 2, 3, 4, 5])],
            'regret' => ['nullable', 'boolean'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    protected function prepareForValidation(): void
    {
        // Los checkboxes/selectores llegan como '1'/'0'/'' — se normaliza a
        // true/false/null para que la validación `boolean` lo acepte.
        if ($this->has('regret')) {
            $value = $this->input('regret');
            if ($value === '' || $value === null) {
                $this->merge(['regret' => null]);
            }
        }
    }
}
