<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DebtPaymentType;
use Database\Factories\DebtPaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pago registrado contra una deuda (Épica 6). Es la fuente de verdad del
 * saldo (ADR-0020) y, si salió de una cuenta del hogar, enlaza el gasto
 * real que movió ese saldo (ADR-0021).
 */
#[Fillable(['amount', 'date', 'type', 'notes'])]
class DebtPayment extends Model
{
    /** @use HasFactory<DebtPaymentFactory> */
    use HasFactory;

    /**
     * household_id, debt_id y expense_id NO son fillable: los asigna
     * DebtService, nunca la petición.
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'date' => 'date:Y-m-d',
            'type' => DebtPaymentType::class,
        ];
    }

    public function debt(): BelongsTo
    {
        return $this->belongsTo(Debt::class);
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    /** Gasto generado, si el pago salió de una cuenta del hogar. */
    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }
}
