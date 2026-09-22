<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ReceivablePaymentType;
use Database\Factories\ReceivablePaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cobro registrado contra una cuenta por cobrar (Épica 15). Es la fuente
 * de verdad del saldo (ADR-0020 espejo) y, si entró a una cuenta del
 * hogar, enlaza el ingreso real que movió ese saldo (ADR-0021 espejo).
 */
#[Fillable(['amount', 'date', 'type', 'notes'])]
class ReceivablePayment extends Model
{
    /** @use HasFactory<ReceivablePaymentFactory> */
    use HasFactory;

    /**
     * household_id, receivable_id, account_id e income_id NO son fillable:
     * los asigna ReceivableService, nunca la petición.
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'date' => 'date:Y-m-d',
            'type' => ReceivablePaymentType::class,
        ];
    }

    public function receivable(): BelongsTo
    {
        return $this->belongsTo(Receivable::class);
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /** Ingreso generado, si el cobro entró a una cuenta del hogar. */
    public function income(): BelongsTo
    {
        return $this->belongsTo(Income::class);
    }
}
