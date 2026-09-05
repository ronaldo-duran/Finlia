<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Formateo centralizado de dinero y porcentajes (ADR-0006, ADR-0010).
 *
 * Blade (@money / @percent) y un futuro JSON de la API comparten estos
 * helpers, así que el formato se fija aquí y no vista por vista.
 */
class FormatHelpersTest extends TestCase
{
    /**
     * @return array<string, array{int|float|null, string}>
     */
    public static function montos(): array
    {
        return [
            'entero con miles' => [1000000, '$ 1.000.000,00'],
            'con decimales' => [1234.5, '$ 1.234,50'],
            'cero' => [0, '$ 0,00'],
            'null se trata como cero' => [null, '$ 0,00'],
            'negativo' => [-2500, '$ -2.500,00'],
        ];
    }

    #[DataProvider('montos')]
    public function test_money_usa_punto_para_miles_y_coma_para_decimales(int|float|null $input, string $esperado): void
    {
        $this->assertSame($esperado, money($input));
    }

    /**
     * @return array<string, array{int|float|null, int, string}>
     */
    public static function porcentajes(): array
    {
        return [
            // Los enteros no arrastran decimales (mismo criterio que @money).
            'entero sin decimales' => [80, 1, '80 %'],
            'cero' => [0, 1, '0 %'],
            'null se trata como cero' => [null, 1, '0 %'],
            'un decimal' => [332.4, 1, '332,4 %'],
            'redondea al decimal pedido' => [0.05, 1, '0,1 %'],

            // Tasas de interés: hasta 3 decimales, sin ceros de relleno.
            'tasa con un decimal' => [12.5, 3, '12,5 %'],
            'tasa con dos decimales' => [12.75, 3, '12,75 %'],
            'tasa entera' => [13, 3, '13 %'],

            // El separador de miles no debe confundirse con el decimal al
            // recortar ceros: "1.200" no puede quedar en "1.2".
            'miles sin decimales' => [1200, 3, '1.200 %'],
            'miles con decimales' => [1200.5, 3, '1.200,5 %'],
        ];
    }

    #[DataProvider('porcentajes')]
    public function test_percent_recorta_ceros_sin_romper_los_miles(int|float|null $input, int $decimales, string $esperado): void
    {
        $this->assertSame($esperado, percent($input, $decimales));
    }

    public function test_percent_equivale_al_formateo_manual_que_reemplaza(): void
    {
        // Antes cada vista repetía esta expresión para las tasas de interés.
        $manual = fn (float $rate): string => str_replace(
            '.', ',', rtrim(rtrim(number_format($rate, 3, '.', ''), '0'), '.')
        ).' %';

        foreach ([0.0, 5.0, 12.5, 12.75, 13.0, 28.999] as $rate) {
            $this->assertSame($manual($rate), percent($rate, 3), "Difiere para {$rate}");
        }
    }
}
