<?php

declare(strict_types=1);

namespace App\Http\Requests\Income;

use App\Models\Income;

/**
 * La edición de un ingreso valida exactamente igual que el alta.
 *
 * Autoriza ANTES de validar (aislamiento multi-hogar, ADR-0005): el recurso
 * ruta-vinculado ({income}) ya está resuelto, así que la Policy decide 403
 * antes de que las reglas evalúen account_id/category_id. Así un usuario ajeno
 * siempre recibe 403 —sin importar los datos enviados— y no se filtra si un
 * account_id pertenece o no a su hogar.
 */
class UpdateIncomeRequest extends StoreIncomeRequest
{
    public function authorize(): bool
    {
        $income = $this->route('income');

        return $income instanceof Income && ($this->user()?->can('update', $income) ?? false);
    }
}
