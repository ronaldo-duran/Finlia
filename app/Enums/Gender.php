<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Género del usuario (Plan 04, ADR-0032). Opcional SIEMPRE: NULL (el
 * default del select, "Prefiero no decirlo") no almacena nada — la mejor
 * forma de minimizar un dato sensible (Ley 1581) es no tenerlo.
 *
 * Sin inferencias automáticas: solo lo que la persona declara, y nada de
 * lógica que lo use para nada más allá de analítica agregada.
 */
enum Gender: string
{
    case Female = 'female';
    case Male = 'male';
    case NonBinary = 'non_binary';

    public function label(): string
    {
        return match ($this) {
            self::Female => 'Mujer',
            self::Male => 'Hombre',
            self::NonBinary => 'No binario',
        };
    }

    /**
     * Opciones para un <select>: [valor => etiqueta].
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        $labels = [];
        foreach (self::cases() as $case) {
            $labels[$case->value] = $case->label();
        }

        return $labels;
    }
}
