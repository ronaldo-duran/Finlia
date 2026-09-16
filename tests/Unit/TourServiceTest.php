<?php

namespace Tests\Unit;

use App\Enums\TourStatus;
use App\Models\User;
use App\Services\TourService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El mecanismo de novedades de las guías (ADR-0045).
 *
 * Es la razón de ser del versionado: cuando se reescribe una guía para
 * presentar algo nuevo, quien ya la vio debe recibir SOLO lo nuevo. Se prueba
 * contra un registro de mentira para que estas pruebas no se rompan cada vez
 * que se retoque el texto de una guía real.
 */
class TourServiceTest extends TestCase
{
    use RefreshDatabase;

    private TourService $tours;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tours = app(TourService::class);
    }

    /** Registro de mentira con dos pasos originales y uno añadido en la v2. */
    private function registroDePrueba(int $version): void
    {
        config(['tours' => [
            'demo' => [
                'title' => 'Demo',
                'icon' => 'bi-compass',
                'summary' => 'Guía de prueba.',
                'route' => 'dashboard',
                'link' => 'dashboard',
                'version' => $version,
                'auto' => true,
                'steps' => [
                    ['since' => 1, 'anchor' => null, 'title' => 'Uno', 'body' => 'Primero'],
                    ['since' => 1, 'anchor' => null, 'title' => 'Dos', 'body' => 'Segundo'],
                    ['since' => 2, 'anchor' => null, 'title' => 'Tres', 'body' => 'Lo nuevo'],
                ],
            ],
        ]]);
    }

    public function test_quien_no_la_ha_visto_tiene_todos_los_pasos_pendientes(): void
    {
        $this->registroDePrueba(version: 2);
        $user = User::factory()->create();

        $this->assertSame(3, $this->tours->pendingStepCount($user, 'demo'));
        $this->assertTrue($this->tours->shouldAutoStart($user, 'demo'));
    }

    public function test_tras_verla_no_queda_nada_pendiente(): void
    {
        $this->registroDePrueba(version: 1);
        $user = User::factory()->create();

        $this->tours->markSeen($user, 'demo', TourStatus::Completed);

        // El paso 'since' => 2 todavía no cuenta: la guía sigue en la v1, así
        // que ese paso aún no se ha publicado.
        $this->assertSame(0, $this->tours->pendingStepCount($user, 'demo'));
        $this->assertFalse($this->tours->shouldAutoStart($user, 'demo'));
    }

    public function test_un_paso_sin_publicar_no_se_escapa(): void
    {
        // La guía va por la v1 y el tercer paso está escrito para la v2: ni
        // cuenta como pendiente ni viaja al navegador, donde el motor lo
        // pintaría sin más.
        $this->registroDePrueba(version: 1);
        $user = User::factory()->create();

        $this->assertSame(2, $this->tours->pendingStepCount($user, 'demo'));
        $this->assertCount(2, $this->tours->payloadFor($user, 'demo')['steps']);
    }

    public function test_al_publicar_la_v2_solo_queda_pendiente_el_paso_nuevo(): void
    {
        $this->registroDePrueba(version: 1);
        $user = User::factory()->create();
        $this->tours->markSeen($user, 'demo', TourStatus::Completed);

        // Se reescribe la guía: sube a la v2 y aparece el paso nacido en la v2.
        $this->registroDePrueba(version: 2);

        $this->assertSame(1, $this->tours->pendingStepCount($user, 'demo'));
        $this->assertTrue($this->tours->shouldAutoStart($user, 'demo'));

        // El payload sigue llevando los tres pasos —el navegador los necesita
        // todos para cuando la pidan entera desde el menú— más la versión ya
        // vista, que es con lo que filtra.
        $payload = $this->tours->payloadFor($user, 'demo');
        $this->assertCount(3, $payload['steps']);
        $this->assertSame(1, $payload['seen']);
    }

    public function test_quien_llega_nuevo_a_la_v2_recibe_la_guia_entera(): void
    {
        $this->registroDePrueba(version: 2);
        $user = User::factory()->create();

        $this->assertSame(3, $this->tours->pendingStepCount($user, 'demo'));
        $this->assertSame(0, $this->tours->payloadFor($user, 'demo')['seen']);
    }

    public function test_subir_la_version_sin_pasos_nuevos_no_reabre_la_guia(): void
    {
        $this->registroDePrueba(version: 1);
        $user = User::factory()->create();
        $this->tours->markSeen($user, 'demo', TourStatus::Completed);

        // Descuido típico: se corrige una errata y se sube la versión por
        // inercia. No hay nada nuevo que enseñar, así que no debe reaparecer.
        config(['tours.demo.version' => 2]);
        config(['tours.demo.steps' => [
            ['since' => 1, 'anchor' => null, 'title' => 'Uno', 'body' => 'Primero (corregido)'],
        ]]);

        $this->assertSame(0, $this->tours->pendingStepCount($user, 'demo'));
        $this->assertFalse($this->tours->shouldAutoStart($user, 'demo'));
    }

    public function test_marcar_vista_guarda_la_version_publicada(): void
    {
        $this->registroDePrueba(version: 2);
        $user = User::factory()->create();

        $this->tours->markSeen($user, 'demo', TourStatus::Skipped);

        $this->assertSame(2, $this->tours->versionSeen($user, 'demo'));
    }

    public function test_reiniciar_devuelve_todo_a_pendiente(): void
    {
        $this->registroDePrueba(version: 2);
        $user = User::factory()->create();
        $this->tours->markSeen($user, 'demo', TourStatus::Completed);

        $this->tours->reset($user);

        $this->assertSame(0, $this->tours->versionSeen($user, 'demo'));
        $this->assertSame(3, $this->tours->pendingStepCount($user, 'demo'));
    }

    // -------------------------------------------------------------- registro

    public function test_la_ruta_se_resuelve_con_comodin(): void
    {
        config(['tours' => [
            'deudas' => [
                'title' => 'Deudas', 'icon' => 'bi-x', 'summary' => '',
                'route' => 'debts.*', 'link' => 'debts.index',
                'version' => 1, 'auto' => true, 'steps' => [],
            ],
        ]]);

        // El detalle de una deuda es la misma pantalla que el listado.
        $this->assertSame('deudas', $this->tours->keyForRoute('debts.index'));
        $this->assertSame('deudas', $this->tours->keyForRoute('debts.show'));
        $this->assertNull($this->tours->keyForRoute('dashboard'));
        $this->assertNull($this->tours->keyForRoute(null));
    }

    public function test_una_clave_fuera_del_registro_no_existe(): void
    {
        $this->registroDePrueba(version: 1);
        $user = User::factory()->create();

        $this->assertNull($this->tours->find('inventada'));
        $this->assertNull($this->tours->payloadFor($user, 'inventada'));
        $this->assertSame(0, $this->tours->pendingStepCount($user, 'inventada'));
        $this->assertFalse($this->tours->shouldAutoStart($user, 'inventada'));

        // Y marcarla no deja rastro: sin fila, sin tabla que llenar.
        $this->tours->markSeen($user, 'inventada', TourStatus::Completed);
        $this->assertDatabaseCount('user_tours', 0);
    }

    // ---------------------------------------------------------- guías reales

    public function test_el_registro_real_esta_bien_formado(): void
    {
        // Una guía mal escrita en config/tours.php no la ve nadie hasta que un
        // usuario llega a esa pantalla. Esto la caza al ejecutar las pruebas.
        $rutas = collect(app('router')->getRoutes())->map->getName()->filter()->all();

        foreach (require config_path('tours.php') as $key => $guia) {
            foreach (['title', 'icon', 'summary', 'route', 'link', 'version', 'auto', 'steps'] as $campo) {
                $this->assertArrayHasKey($campo, $guia, "La guía «{$key}» no declara «{$campo}».");
            }

            // La ruta de la pantalla tiene que existir, y ser EXACTA: un
            // comodín casaría con pantallas donde no están sus anclajes.
            foreach ((array) $guia['route'] as $ruta) {
                $this->assertStringNotContainsString('*', $ruta, "La guía «{$key}» usa un comodín en «route».");
                $this->assertContains($ruta, $rutas, "La guía «{$key}» apunta a una ruta inexistente.");
            }

            // Sin enlace (pantallas con id en la URL) hace falta decir dónde
            // está, o el catálogo del perfil la lista sin salida.
            if ($guia['link'] === null) {
                $this->assertNotEmpty($guia['link_hint'] ?? null, "La guía «{$key}» no se puede enlazar y no dice dónde está.");
            } else {
                $this->assertContains($guia['link'], $rutas, "La guía «{$key}» enlaza a una ruta inexistente.");
            }

            $this->assertNotEmpty($guia['steps'], "La guía «{$key}» no tiene pasos.");

            foreach ($guia['steps'] as $i => $paso) {
                $this->assertArrayHasKey('since', $paso, "Paso {$i} de «{$key}» sin «since».");
                $this->assertNotEmpty($paso['title'], "Paso {$i} de «{$key}» sin titular.");
                $this->assertNotEmpty($paso['body'], "Paso {$i} de «{$key}» sin texto.");
            }
        }
    }

    public function test_ninguna_pantalla_tiene_dos_guias(): void
    {
        // keyForRoute() devuelve la primera que case, así que dos guías sobre la
        // misma ruta dejarían una muerta sin que nadie se entere.
        $rutas = [];

        foreach (require config_path('tours.php') as $key => $guia) {
            foreach ((array) $guia['route'] as $ruta) {
                $this->assertArrayNotHasKey(
                    $ruta,
                    $rutas,
                    "«{$key}» y «".($rutas[$ruta] ?? '')."» se disputan la ruta «{$ruta}».",
                );
                $rutas[$ruta] = $key;
            }
        }

        $this->assertNotEmpty($rutas);
    }
}
