<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Tipo de deuda (Épica 6).
 */
enum DebtType: string
{
    case CreditCard = 'credit_card';
    case Loan = 'loan';
    case Vehicle = 'vehicle';
    case Mortgage = 'mortgage';
    case Family = 'family';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::CreditCard => 'Tarjeta de crédito',
            self::Loan => 'Préstamo',
            self::Vehicle => 'Crédito de vehículo',
            self::Mortgage => 'Crédito hipotecario',
            self::Family => 'Préstamo familiar',
            self::Other => 'Otra',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::CreditCard => 'bi-credit-card',
            self::Loan => 'bi-bank',
            self::Vehicle => 'bi-car-front',
            self::Mortgage => 'bi-house-door',
            self::Family => 'bi-people',
            self::Other => 'bi-cash-stack',
        };
    }

    /**
     * ¿Este tipo admite los atributos de tarjeta (cupo, corte, cuota de manejo)?
     */
    public function isCard(): bool
    {
        return $this === self::CreditCard;
    }

    /**
     * Número máximo de cuotas razonable para este tipo de deuda (ADR-0022).
     *
     * Son topes **prácticos** del mercado colombiano para evitar errores de
     * dedo (escribir 1200 en vez de 120), no límites legales: ninguna norma
     * fija estos números y cada entidad tiene los suyos. Se es deliberadamente
     * generoso con el máximo para no bloquear un caso real.
     */
    public function maxTermMonths(): int
    {
        return match ($this) {
            self::CreditCard => 100,
            self::Vehicle => 96,
            self::Mortgage => 480,
            self::Loan, self::Family, self::Other => 120,
        };
    }

    /**
     * Topes por tipo, para la validación y para el formulario.
     *
     * @return array<string, int>
     */
    public static function termLimits(): array
    {
        $limits = [];

        foreach (self::cases() as $case) {
            $limits[$case->value] = $case->maxTermMonths();
        }

        return $limits;
    }
}
