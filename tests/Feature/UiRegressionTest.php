<?php

namespace Tests\Feature;

use App\Models\Debt;
use App\Models\User;
use App\Services\HouseholdService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regresiones de interfaz que no se ven en un test funcional normal: un icono
 * inexistente no rompe nada (simplemente no se pinta) y un mes en inglés pasa
 * desapercibido si el entorno de test tiene el locale "correcto".
 */
class UiRegressionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Un `bi-*` mal escrito no da error: Bootstrap Icons no encuentra la clase
     * y no dibuja nada. Se comprueba contra la lista real del paquete.
     */
    public function test_todos_los_iconos_usados_existen_en_bootstrap_icons(): void
    {
        $css = base_path('node_modules/bootstrap-icons/font/bootstrap-icons.css');

        if (! is_file($css)) {
            $this->markTestSkipped('bootstrap-icons no instalado (requiere npm install).');
        }

        preg_match_all('/^\.(bi-[a-z0-9-]+)::before/m', file_get_contents($css), $m);
        $existentes = array_flip($m[1]);

        $usados = [];
        $directorios = [resource_path('views'), app_path()];

        foreach ($directorios as $dir) {
            $archivos = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
            foreach ($archivos as $archivo) {
                if (! $archivo->isFile() || ! str_ends_with($archivo->getFilename(), '.php')) {
                    continue;
                }
                preg_match_all('/\bbi-[a-z0-9-]+/', file_get_contents($archivo->getPathname()), $encontrados);
                foreach ($encontrados[0] as $icono) {
                    $usados[$icono] = $archivo->getPathname();
                }
            }
        }

        $this->assertNotEmpty($usados, 'No se encontró ningún icono: revisa el escaneo.');

        $inexistentes = [];
        foreach ($usados as $icono => $donde) {
            // `bi-` suelto es el prefijo de la clase base, no un icono.
            if ($icono !== 'bi' && ! isset($existentes[$icono])) {
                $inexistentes[] = $icono.' ('.str_replace(base_path().'/', '', $donde).')';
            }
        }

        $this->assertSame([], $inexistentes, "Iconos que no existen y no se pintarán:\n".implode("\n", $inexistentes));
    }

    /**
     * La proyección de deuda mostraba el mes en inglés porque `translatedFormat`
     * usa el locale GLOBAL de Carbon, que se sincroniza con APP_LOCALE. Con
     * APP_LOCALE=en el usuario veía "December de 2028".
     */
    public function test_el_mes_de_la_proyeccion_sale_en_espanol_aunque_la_app_este_en_ingles(): void
    {
        // Se fuerza el escenario del usuario: aplicación en inglés.
        $this->app->setLocale('en');
        Carbon::setLocale('en');

        $owner = User::factory()->create();
        $household = app(HouseholdService::class)->createHousehold($owner->id, 'Hogar A');
        $debt = Debt::factory()->create([
            'household_id' => $household->id,
            'name' => 'Crédito',
            'original_amount' => 1200000,
            'current_balance' => 1200000,
            'interest_rate' => 0,
            'scheduled_payment' => 100000, // 12 cuotas exactas
            'minimum_payment' => null,
            'due_day' => 15,
        ]);

        foreach ([route('debts.show', $debt), route('debts.index')] as $url) {
            $html = $this->actingAs($owner)->get($url)->assertOk()->getContent();

            foreach (['January', 'February', 'March', 'April', 'May', 'June', 'July',
                'August', 'September', 'October', 'November', 'December'] as $mesIngles) {
                $this->assertStringNotContainsString(
                    $mesIngles,
                    $html,
                    "La proyección muestra «{$mesIngles}» en inglés en {$url}.",
                );
            }
        }
    }
}
