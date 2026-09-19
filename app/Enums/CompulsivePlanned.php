<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * "¿Este gasto lo tenías previsto?" — paso 1 del árbol de decisión de
 * compras (Épica 12, v0.39).
 */
enum CompulsivePlanned: string
{
    case Yes = 'yes';
    case Partial = 'partial';
    case No = 'no';

    public function label(): string
    {
        return match ($this) {
            self::Yes => 'Sí',
            self::Partial => 'Más o menos',
            self::No => 'No',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
