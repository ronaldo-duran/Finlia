<?php

declare(strict_types=1);

namespace App\Http\Requests\Debt;

/**
 * Valida la edición de una deuda. Mismas reglas que el alta: no se puede
 * tocar el saldo a mano (ADR-0020) ni el hogar.
 */
class UpdateDebtRequest extends StoreDebtRequest {}
