<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ReceivablePaymentType;
use App\Enums\ReceivableStatus;
use App\Models\Household;
use App\Models\Receivable;
use App\Models\ReceivablePayment;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Lógica de cuentas por cobrar (Épica 15): saldos, cobros y el resumen
 * que alimenta el panel y la tarjeta del dashboard.
 *
 * Seam (ADR-0010): no depende de la capa HTTP. Recibe IDs, modelos y
 * arrays explícitos y devuelve estructuras serializables, así que la
 * futura API (Épica 14) lo reutiliza tal cual.
 */
class ReceivableService
{
    public function __construct(private readonly MovementService $movements) {}

    /**
     * Crea una cuenta por cobrar. El saldo arranca en el importe original,
     * no lo teclea el usuario (ADR-0020 espejo).
     *
     * @param  array<string, mixed>  $data
     */
    public function createReceivable(Household $household, array $data): Receivable
    {
        $receivable = $household->receivables()->make($data);
        $receivable->current_balance = $data['original_amount'];
        $receivable->status ??= ReceivableStatus::Pending->value;
        $receivable->save();

        return $receivable;
    }

    /**
     * Actualiza los datos editables y recalcula el saldo, porque cambiar
     * el importe original mueve su línea base.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateReceivable(Receivable $receivable, array $data): Receivable
    {
        $receivable->fill($data);
        $receivable->save();

        return $this->recomputeBalance($receivable);
    }

    /**
     * Recalcula y persiste el saldo: importe original menos cobros
     * registrados. Nunca baja de cero. Los estados se mueven así:
     *
     *  - saldo == original → pending.
     *  - 0 < saldo < original → partial.
     *  - saldo == 0 → paid.
     *  - written_off queda intacto: el usuario lo marcó a mano.
     */
    public function recomputeBalance(Receivable $receivable): Receivable
    {
        $paid = (float) $receivable->payments()->sum('amount');
        $original = (float) $receivable->original_amount;
        $balance = round(max(0.0, $original - $paid), 2);

        $attributes = ['current_balance' => $balance];

        if ($receivable->status !== ReceivableStatus::WrittenOff) {
            if ($balance <= 0.0) {
                $attributes['status'] = ReceivableStatus::Paid;
            } elseif ($balance < $original) {
                $attributes['status'] = ReceivableStatus::Partial;
            } else {
                $attributes['status'] = ReceivableStatus::Pending;
            }
        }

        $receivable->forceFill($attributes)->save();

        return $receivable;
    }

    /**
     * Registra un cobro contra la cuenta por cobrar y recalcula el saldo.
     *
     * Si se indica una cuenta del hogar y el tipo es "cobro recibido",
     * además crea el ingreso real que mueve su saldo (ADR-0021 espejo),
     * todo dentro de una misma transacción. Condonaciones y ajustes
     * reducen el saldo sin ingreso real.
     *
     * @param  array<string, mixed>  $data  amount, date, type, notes, account_id?, category_id?
     */
    public function registerPayment(Receivable $receivable, array $data, User $user): ReceivablePayment
    {
        return DB::transaction(function () use ($receivable, $data, $user): ReceivablePayment {
            $type = ReceivablePaymentType::from((string) ($data['type'] ?? ReceivablePaymentType::Received->value));

            $income = null;
            $accountId = $data['account_id'] ?? null;

            if ($accountId !== null && $type->createsIncome()) {
                $income = $this->movements->createIncome([
                    'account_id' => $accountId,
                    'category_id' => $data['category_id'] ?? null,
                    'amount' => $data['amount'],
                    'date' => $data['date'],
                    'description' => $receivable->name.' (cobro)',
                    'notes' => $data['notes'] ?? null,
                ], $receivable->household, $user);
            }

            $payment = $receivable->payments()->make([
                'amount' => $data['amount'],
                'date' => $data['date'],
                'type' => $type->value,
                'notes' => $data['notes'] ?? null,
            ]);

            $payment->household_id = $receivable->household_id;
            $payment->account_id = $type->createsIncome() ? $accountId : null;
            $payment->income_id = $income?->id;
            $payment->save();

            $this->recomputeBalance($receivable);

            return $payment;
        });
    }

    /**
     * Borra un cobro y deshace su efecto: elimina el ingreso asociado
     * (lo que baja el saldo de la cuenta) y recalcula la cuenta por cobrar.
     */
    public function deletePayment(ReceivablePayment $payment): void
    {
        DB::transaction(function () use ($payment): void {
            $receivable = $payment->receivable;
            $income = $payment->income;

            $payment->delete();

            if ($income !== null) {
                $this->movements->deleteIncome($income);
            }

            $this->recomputeBalance($receivable);
        });
    }

    /**
     * Resumen del hogar: total pendiente, importe original y progreso de
     * cobro. Solo cuentan las que aún pesan (pendientes y parciales).
     *
     * @return array<string, mixed>
     */
    public function summary(int $householdId): array
    {
        /** @var Collection<int, Receivable> $receivables */
        $receivables = Receivable::where('household_id', $householdId)->outstanding()->get();

        $totalBalance = round((float) $receivables->sum(fn (Receivable $r) => (float) $r->current_balance), 2);
        $totalOriginal = round((float) $receivables->sum(fn (Receivable $r) => (float) $r->original_amount), 2);

        return [
            'total_balance' => $totalBalance,
            'total_original' => $totalOriginal,
            'total_collected' => round(max(0.0, $totalOriginal - $totalBalance), 2),
            'progress_percent' => $totalOriginal > 0.0
                ? round(max(0.0, min(100.0, ($totalOriginal - $totalBalance) / $totalOriginal * 100)), 1)
                : 0.0,
            'count' => $receivables->count(),
        ];
    }

    /**
     * Cuentas por cobrar del hogar ordenadas por fecha tentativa de cobro,
     * las más próximas primero; las sin fecha van al final.
     *
     * @return Collection<int, Receivable>
     */
    public function outstandingForHousehold(int $householdId): Collection
    {
        return Receivable::where('household_id', $householdId)
            ->outstanding()
            ->orderByRaw('due_date IS NULL')
            ->orderBy('due_date')
            ->orderBy('name')
            ->get();
    }
}
