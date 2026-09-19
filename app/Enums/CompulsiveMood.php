<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * "¿Cómo te sientes ahora?" — paso 3 del árbol. Escala de 1 a 5 en
 * iconos Bootstrap (mismo lenguaje visual que el resto de la app);
 * se guarda por valor entero para permitir promedios.
 */
enum CompulsiveMood: int
{
    case VerySad = 1;
    case Sad = 2;
    case Neutral = 3;
    case Happy = 4;
    case VeryHappy = 5;

    /**
     * Clase Bootstrap Icons que representa el ánimo. Se dibuja con
     * `<i class="bi {{ $mood->iconClass() }}"></i>`. Se prefieren los
     * variantes `-fill` para que en Bootstrap 5 tengan mejor peso visual.
     */
    public function iconClass(): string
    {
        return match ($this) {
            self::VerySad => 'bi-emoji-frown',
            self::Sad => 'bi-emoji-expressionless',
            self::Neutral => 'bi-emoji-neutral',
            self::Happy => 'bi-emoji-smile',
            self::VeryHappy => 'bi-emoji-laughing',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::VerySad => 'Muy mal',
            self::Sad => 'Mal',
            self::Neutral => 'Normal',
            self::Happy => 'Bien',
            self::VeryHappy => 'Muy bien',
        };
    }
}
