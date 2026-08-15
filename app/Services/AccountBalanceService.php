<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Account;

/**
 * Recalcula el saldo de una cuenta desde la fuente de verdad (movimientos).
 *
 * ADR-0012: current_balance se persiste y se RECOMPUTA (no incremental) en
 * cada alta/edición/borrado de movimiento, dentro de la transacción. Así no
 * deriva nunca respecto a initial_balance + Σingresos − Σgastos.
 *
 * Seam (ADR-0010): no depende de la capa HTTP.
 */
class AccountBalanceService
{
    /**
     * Recalcula y persiste el saldo actual de la cuenta.
     * Los movimientos soft-deleted quedan excluidos automáticamente (SoftDeletes).
     */
    public function recompute(Account $account): void
    {
        $incomes = (float) $account->incomes()->sum('amount');
        $expenses = (float) $account->expenses()->sum('amount');
        $initial = (float) $account->initial_balance;

        $account->current_balance = $initial + $incomes - $expenses;
        $account->save();
    }

    /**
     * Recalcula varias cuentas (útil al reasignar cuenta en una edición).
     *
     * @param  iterable<int>  $accountIds
     */
    public function recomputeMany(iterable $accountIds): void
    {
        foreach (array_unique(iterator_to_array($accountIds)) as $id) {
            $account = Account::find($id);
            if ($account !== null) {
                $this->recompute($account);
            }
        }
    }
}
