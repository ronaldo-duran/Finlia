<?php

declare(strict_types=1);

namespace App\Http\Requests\Expense;

use App\Models\Expense;

/**
 * La edición de un gasto valida exactamente igual que el alta.
 *
 * Autoriza ANTES de validar (aislamiento multi-hogar, ADR-0005): el recurso
 * ruta-vinculado ({expense}) ya está resuelto, así que la Policy decide 403
 * antes de que las reglas evalúen account_id/category_id. Así un usuario ajeno
 * siempre recibe 403 —sin importar los datos enviados— y no se filtra si un
 * account_id pertenece o no a su hogar.
 */
class UpdateExpenseRequest extends StoreExpenseRequest
{
    public function authorize(): bool
    {
        $expense = $this->route('expense');

        return $expense instanceof Expense && ($this->user()?->can('update', $expense) ?? false);
    }
}
