<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * "¿Cómo te sientes ahora?" — paso 3 del árbol. Escala de 1 a 5 en
 * emojis; se guarda por valor entero para permitir promedios.
 */
enum CompulsiveMood: int
{
    case VerySad = 1;
    case Sad = 2;
    case Neutral = 3;
    case Happy = 4;
    case VeryHappy = 5;

    public function emoji(): string
    {
        return match ($this) {
            self::VerySad => '😔',
            self::Sad => '😐',
            self::Neutral => '🙂',
            self::Happy => '😄',
            self::VeryHappy => '🤩',
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
