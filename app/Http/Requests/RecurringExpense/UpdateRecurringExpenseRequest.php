<?php

declare(strict_types=1);

namespace App\Http\Requests\RecurringExpense;

use App\Models\RecurringExpense;

/**
 * Edición de un recurrente: mismas reglas que el alta.
 *
 * Autoriza ANTES de validar (aislamiento multi-hogar, ADR-0005), igual que
 * UpdateExpenseRequest: el recurso ruta-vinculado ya está resuelto, así que la
 * Policy decide 403 antes de que las reglas evalúen account_id/category_id.
 * Así un usuario ajeno recibe 403 sin importar los datos enviados, y las
 * reglas —que se acotan al hogar activo— solo llegan a ejecutarse cuando ese
 * hogar es el mismo que el del recurso.
 */
class UpdateRecurringExpenseRequest extends StoreRecurringExpenseRequest
{
    public function authorize(): bool
    {
        $recurringExpense = $this->route('recurringExpense');

        return $recurringExpense instanceof RecurringExpense
            && ($this->user()?->can('update', $recurringExpense) ?? false);
    }
}
