<?php

declare(strict_types=1);

namespace App\Http\Requests\Budget;

use App\Enums\BudgetPeriod;
use App\Enums\CategoryType;
use App\Models\Budget;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Valida la creación de un presupuesto mensual.
 * No acepta household_id (se toma del hogar activo) ni period (solo 'monthly'
 * en la Épica 4). category_id se acota a categorías de gasto visibles por el
 * hogar (aislamiento, ADR-0005).
 */
class StoreBudgetRequest extends FormRequest
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
            // NULL = presupuesto total del mes.
            'category_id' => [
                'nullable',
                Rule::exists('categories', 'id')->where(fn ($q) => $q
                    ->where('type', CategoryType::Expense->value)
                    ->where(fn ($q2) => $q2->whereNull('household_id')->orWhere('household_id', $householdId))),
            ],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:9999999999999.99'],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'month' => ['required', 'integer', 'between:1,12'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.min' => 'El monto del presupuesto debe ser mayor que cero.',
            'month.between' => 'El mes debe estar entre 1 y 12.',
        ];
    }

    /**
     * Un solo presupuesto por hogar/categoría/mes. La comprobación va aquí
     * (y no solo con Rule::unique) porque el presupuesto total tiene
     * category_id NULL: MySQL considera los NULL distintos en un índice único
     * y `nullable` desactiva las demás reglas cuando el valor es null.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $categoryId = $this->input('category_id');

            $exists = Budget::query()
                ->where('household_id', active_household_id())
                ->where('period', BudgetPeriod::Monthly->value)
                ->where('year', (int) $this->input('year'))
                ->where('month', (int) $this->input('month'))
                ->when(
                    $categoryId,
                    fn ($q) => $q->where('category_id', $categoryId),
                    fn ($q) => $q->whereNull('category_id'),
                )
                ->exists();

            if ($exists) {
                $validator->errors()->add('category_id', $categoryId
                    ? 'Ya existe un presupuesto para esa categoría en ese mes. Edítalo en lugar de crear otro.'
                    : 'Ya existe un presupuesto total para ese mes. Edítalo en lugar de crear otro.');
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function validatedData(): array
    {
        $data = $this->validated();
        $data['category_id'] = $data['category_id'] ?? null;
        // La periodicidad no la elige el usuario: la Épica 4 solo es mensual.
        $data['period'] = BudgetPeriod::Monthly->value;

        return $data;
    }
}
