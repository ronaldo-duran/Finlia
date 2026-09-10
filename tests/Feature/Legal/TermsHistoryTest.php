<?php

namespace Tests\Feature\Legal;

use App\Models\TermsVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Historial público de versiones de los términos.
 *
 * Los propios términos prometen que «las versiones anteriores quedan
 * consultables públicamente». Antes existía una URL por versión pero ningún
 * índice, así que había que adivinar el identificador: la promesa estaba
 * escrita y no se cumplía.
 */
class TermsHistoryTest extends TestCase
{
    use RefreshDatabase;

    private function publicar(string $version, string $fecha, ?string $resumen = null): TermsVersion
    {
        return TermsVersion::create([
            'version' => $version,
            'title' => 'Términos y condiciones de uso',
            'change_summary' => $resumen,
            'published_at' => $fecha,
            'content' => "Contenido de la versión {$version}.",
        ]);
    }

    public function test_el_historial_es_publico_y_lista_todas_las_versiones(): void
    {
        $this->publicar('2026-09-v1', '2026-09-10');
        $this->publicar('2026-12-v1', '2026-12-01', 'Se aclara el plazo de eliminación.');

        $this->get(route('terms.history'))
            ->assertOk()
            ->assertSee('2026-09-v1')
            ->assertSee('2026-12-v1')
            ->assertSee('Se aclara el plazo de eliminación.');
    }

    public function test_marca_cual_es_la_vigente(): void
    {
        $this->publicar('2026-09-v1', '2026-09-10');
        $this->publicar('2026-12-v1', '2026-12-01');

        $this->get(route('terms.history'))->assertOk()->assertSee('Vigente');
    }

    /**
     * «historial» no puede confundirse con el identificador de una versión:
     * la ruta del índice tiene que estar registrada antes que la comodín.
     */
    public function test_la_ruta_del_indice_gana_a_la_de_una_version(): void
    {
        $this->publicar('2026-09-v1', '2026-09-10');

        $this->get('/terminos/historial')
            ->assertOk()
            ->assertSee('Historial de términos');
    }

    /**
     * La aceptación es la prueba del consentimiento del usuario: tiene derecho
     * a ver qué aceptó y cuándo, sin pedírselo a nadie.
     */
    public function test_a_quien_tiene_sesion_le_muestra_cuando_acepto(): void
    {
        $version = $this->publicar('2026-09-v1', '2026-09-10');
        $usuario = User::factory()->create();
        $usuario->acceptTerms($version, '127.0.0.1');

        $this->actingAs($usuario)
            ->get(route('terms.history'))
            ->assertOk()
            ->assertSee('La aceptaste el');
    }

    public function test_a_un_invitado_no_le_muestra_aceptaciones(): void
    {
        $version = $this->publicar('2026-09-v1', '2026-09-10');
        User::factory()->create()->acceptTerms($version, '127.0.0.1');

        $this->get(route('terms.history'))
            ->assertOk()
            ->assertDontSee('La aceptaste el');
    }

    public function test_sin_ninguna_version_publicada_responde_404(): void
    {
        $this->get(route('terms.history'))->assertNotFound();
    }

    public function test_los_terminos_vigentes_enlazan_al_historial(): void
    {
        $this->publicar('2026-09-v1', '2026-09-10');

        $this->get(route('terms.show'))
            ->assertOk()
            ->assertSee(route('terms.history'), false);
    }
}
