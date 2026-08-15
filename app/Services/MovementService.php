<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Expense;
use App\Models\Household;
use App\Models\Income;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Ruta de escritura de ingresos y gastos.
 *
 * Envuelve cada mutación en una transacción y mantiene el saldo de la(s)
 * cuenta(s) afectada(s) sincronizado vía AccountBalanceService (ADR-0012).
 *
 * Seam (ADR-0010): no lee request()/session()/Auth::id(); recibe datos
 * explícitos (household, user, array validado). Reusable por una futura API.
 */
class MovementService
{
    public function __construct(private readonly AccountBalanceService $balances) {}

    // ---------------- Ingresos ----------------

    /**
     * @param  array<string, mixed>  $data  datos validados (sin household_id/user_id)
     */
    public function createIncome(array $data, Household $household, User $user): Income
    {
        return DB::transaction(function () use ($data, $household, $user): Income {
            $income = $household->incomes()->create([
                ...$data,
                'user_id' => $user->id,
            ]);

            $this->balances->recompute($income->account);

            return $income;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateIncome(Income $income, array $data): Income
    {
        return DB::transaction(function () use ($income, $data): Income {
            $affected = [$income->account_id, (int) ($data['account_id'] ?? $income->account_id)];

            $income->update($data);
            $income->refresh();

            $this->balances->recomputeMany($affected);

            return $income;
        });
    }

    public function deleteIncome(Income $income): void
    {
        DB::transaction(function () use ($income): void {
            $account = $income->account;

            $income->delete();

            $this->balances->recompute($account);
        });
    }

    // ---------------- Gastos ----------------

    /**
     * @param  array<string, mixed>  $data  datos validados (sin household_id/user_id)
     */
    public function createExpense(array $data, Household $household, User $user): Expense
    {
        return DB::transaction(function () use ($data, $household, $user): Expense {
            $expense = $household->expenses()->create([
                ...$data,
                'user_id' => $user->id,
            ]);

            $this->balances->recompute($expense->account);

            return $expense;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateExpense(Expense $expense, array $data): Expense
    {
        return DB::transaction(function () use ($expense, $data): Expense {
            $affected = [$expense->account_id, (int) ($data['account_id'] ?? $expense->account_id)];

            $expense->update($data);
            $expense->refresh();

            $this->balances->recomputeMany($affected);

            return $expense;
        });
    }

    public function deleteExpense(Expense $expense): void
    {
        DB::transaction(function () use ($expense): void {
            $account = $expense->account;

            $expense->delete();

            $this->balances->recompute($account);
        });
    }
}
