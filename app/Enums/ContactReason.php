<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Motivo de un mensaje de contacto.
 *
 * `Bug` es el único que NO se ofrece en el formulario público: un reporte de
 * error se hace desde dentro de la aplicación y con sesión iniciada, para que
 * llegue con el contexto técnico ya adjunto y no haya que preguntárselo a
 * nadie. Los otros tres son la puerta de entrada de quien todavía no tiene
 * cuenta.
 */
enum ContactReason: string
{
    case Partnership = 'partnership';
    case Commercial = 'commercial';
    case Suggestion = 'suggestion';
    case Bug = 'bug';

    public function label(): string
    {
        return match ($this) {
            self::Partnership => 'Alianzas',
            self::Commercial => 'Comercial',
            self::Suggestion => 'Sugerencia',
            self::Bug => 'Reporte de error',
        };
    }

    /**
     * Los motivos que acepta el formulario público.
     *
     * @return array<int, self>
     */
    public static function public(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $motivo) => $motivo !== self::Bug,
        ));
    }
}
