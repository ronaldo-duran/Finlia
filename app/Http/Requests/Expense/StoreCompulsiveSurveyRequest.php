<?php

declare(strict_types=1);

namespace App\Http\Requests\Expense;

use App\Enums\CompulsiveKind;
use App\Enums\CompulsivePlanned;
use App\Enums\CompulsiveTrigger;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

/**
 * Valida las 4 respuestas del árbol de decisión (Épica 12, v0.39).
 * La autorización la resuelve la Policy sobre `Expense`.
 */
class StoreCompulsiveSurveyRequest extends FormRequest
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
            'planned' => ['required', new Enum(CompulsivePlanned::class)],
            'kind' => ['required', new Enum(CompulsiveKind::class)],
            'mood' => ['required', 'integer', Rule::in([1, 2, 3, 4, 5])],
            'trigger' => ['required', new Enum(CompulsiveTrigger::class)],
        ];
    }
}
