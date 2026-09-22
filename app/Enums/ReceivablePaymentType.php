<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Naturaleza de un cobro (Épica 15).
 *
 * - Received: el deudor pagó (con o sin cuenta asociada, ADR-0021 espejo).
 * - Forgiven: se le condonó al deudor. Reduce el saldo sin ingreso real.
 * - Adjustment: corrección manual (error de importe, ajuste contable).
 *   Reduce el saldo sin ingreso real. Auditar los usos: solo lo mueve el
 *   usuario a mano.
 */
enum ReceivablePaymentType: string
{
    case Received = 'received';
    case Forgiven = 'forgiven';
    case Adjustment = 'adjustment';

    public function label(): string
    {
        return match ($this) {
            self::Received => 'Cobro recibido',
            self::Forgiven => 'Condonado',
            self::Adjustment => 'Ajuste',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Received => 'text-bg-success',
            self::Forgiven => 'text-bg-secondary',
            self::Adjustment => 'text-bg-warning',
        };
    }

    /** ¿Este tipo puede generar un ingreso real cuando hay cuenta destino? */
    public function createsIncome(): bool
    {
        return $this === self::Received;
    }
}
