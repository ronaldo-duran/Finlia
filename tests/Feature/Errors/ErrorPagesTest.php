<?php

namespace Tests\Feature\Errors;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Páginas de error y modo mantenimiento (ADR-0041).
 *
 * Lo que de verdad se vigila aquí no es el texto, sino que estas vistas
 * NO dependan de sesión ni de base de datos: son las únicas que tienen que
 * seguir funcionando cuando la aplicación no funciona.
 */
class ErrorPagesTest extends TestCase
{
    /** @return list<array{string}> */
    public static function codigos(): array
    {
        return [['403'], ['404'], ['419'], ['429'], ['500'], ['503']];
    }

    public function test_una_ruta_inexistente_muestra_la_404_de_finlia(): void
    {
        $this->get('/esta-ruta-no-existe')
            ->assertNotFound()
            ->assertSee('Esta página no existe')
            ->assertSee('Finlia');
    }

    /**
     * Sin base de datos configurada: si alguna vista tocara la sesión, el
     * hogar activo o un modelo, esto reventaría.
     */
    #[DataProvider('codigos')]
    public function test_cada_pagina_se_renderiza_sin_sesion_ni_base_de_datos(string $codigo): void
    {
        config(['database.default' => 'no-existe']);

        $html = view("errors.$codigo")->render();

        $this->assertStringContainsString($codigo, $html);
        $this->assertStringContainsString('Finlia', $html);
        $this->assertStringNotContainsString('csrf-token', $html);
    }

    /**
     * El despliegue automático pre-renderiza la 503 con `--render`, que
     * dibuja la vista desde la CONSOLA: sin petición, sin sesión y sin las
     * rutas resueltas. Si alguien mete un `csrf_token()` o un `route()` de
     * más en esa página, el despliegue se cae justo al empezar.
     */
    public function test_el_modo_mantenimiento_prerenderiza_la_503(): void
    {
        // El HTML pre-renderizado viaja en `framework/down`; `maintenance.php`
        // es solo el interruptor que lo sirve desde `public/index.php`.
        $archivo = storage_path('framework/down');

        try {
            Artisan::call('down', ['--render' => 'errors::503', '--retry' => 15]);

            $this->assertFileExists($archivo);
            $plantilla = json_decode(File::get($archivo), true)['template'] ?? '';
            $this->assertStringContainsString('Estamos actualizando Finlia', $plantilla);
            $this->assertStringContainsString('503', $plantilla);
        } finally {
            // Pase lo que pase, la aplicación no se queda en mantenimiento.
            Artisan::call('up');
        }

        $this->assertFileDoesNotExist($archivo);
    }
}
