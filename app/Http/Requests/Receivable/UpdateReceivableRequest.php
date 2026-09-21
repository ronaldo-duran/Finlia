<?php

declare(strict_types=1);

namespace App\Http\Requests\Receivable;

use App\Enums\ReceivableStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Valida la edición de una cuenta por cobrar. Mismas reglas que el alta;
 * `current_balance` sigue sin ser fillable (se recalcula desde los cobros).
 * Editar `due_date` es la forma de posponer el cobro (sin borrado
 * silencioso: el updated_at deja rastro).
 */
class UpdateReceivableRequest extends FormRequest
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
            'debtor_name' => ['required', 'string', 'min:2', 'max:120'],
            'debtor_user_id' => [
                'nullable',
                Rule::exists('household_user', 'user_id')
                    ->where(fn ($q) => $q->where('household_id', $householdId)),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'original_amount' => ['required', 'numeric', 'min:0.01', 'max:9999999999999.99'],
            'currency' => ['nullable', 'string', 'size:3'],
            'status' => ['nullable', Rule::enum(ReceivableStatus::class)],
            'due_date' => ['nullable', 'date', 'before:2100-01-01'],
            'notes' => ['nullable', 'string', 'max:2000'],
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
}
