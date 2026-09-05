<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\TransferFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Transferencia entre cuentas del mismo hogar (Épica 10, ADR-0035).
 *
 * No es ingreso ni gasto: es un movimiento neutro para el P&L del hogar,
 * pero reduce el saldo de `from_account` y lo aumenta en `to_account`.
 *
 * Campos gestionados por el controlador (no en Fillable):
 *   - household_id → del hogar activo en sesión
 *   - user_id      → del usuario autenticado
 */
#[Fillable(['user_id', 'from_account_id', 'to_account_id', 'amount', 'date', 'description', 'notes'])]
class Transfer extends Model
{
    /** @use HasFactory<TransferFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'date' => 'date',
        ];
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function fromAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'from_account_id');
    }

    public function toAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'to_account_id');
    }
}
