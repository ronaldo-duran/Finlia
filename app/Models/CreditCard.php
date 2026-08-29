<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CreditCardFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Atributos propios de una tarjeta de crédito (Épica 6), complementarios a
 * su cuenta con type=credit_card (ADR-0002).
 *
 * ⚠️ Este modelo NO conoce el número de tarjeta, el CVV ni el PIN: esas
 * columnas no existen y no deben añadirse (docs/SECURITY.md §4).
 */
#[Fillable(['credit_limit', 'statement_date', 'payment_due_date', 'annual_fee', 'monthly_fee'])]
class CreditCard extends Model
{
    /** @use HasFactory<CreditCardFactory> */
    use HasFactory;

    /**
     * household_id y account_id NO son fillable: los asigna el controlador.
     * available_credit tampoco: es derivado del cupo menos lo usado.
     */
    protected function casts(): array
    {
        return [
            'credit_limit' => 'decimal:2',
            'available_credit' => 'decimal:2',
            'annual_fee' => 'decimal:2',
            'monthly_fee' => 'decimal:2',
            'statement_date' => 'integer',
            'payment_due_date' => 'integer',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    /**
     * Porcentaje del cupo ya utilizado. Por encima del 30 % se considera
     * uso alto en salud financiera; la vista lo señala.
     */
    public function utilizationPercent(): float
    {
        $limit = (float) $this->credit_limit;

        if ($limit <= 0.0) {
            return 0.0;
        }

        $used = $limit - (float) $this->available_credit;

        return round(max(0.0, min(100.0, $used / $limit * 100)), 1);
    }
}
