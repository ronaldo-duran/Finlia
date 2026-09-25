<?php

namespace Tests\Feature\Marketing;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Historial público de versiones.
 *
 * Es marketing puro: contenido que ayuda al buscador y al usuario a ver que
 * la app entrega. Se renderiza desde CHANGELOG.md, así que el test se ancla
 * al archivo fuente en lugar de reproducir contenido a mano.
 */
class ChangelogPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_pagina_publica_responde_e_incluye_la_version_vigente(): void
    {
        $version = config('finlia.version');

        $this->get(route('changelog.show'))
            ->assertOk()
            ->assertSee('Historial de versiones', false)
            ->assertSee('v'.$version, false);
    }

    public function test_renderiza_encabezados_del_changelog(): void
    {
        $html = $this->get(route('changelog.show'))->assertOk()->getContent();

        $this->assertStringContainsString('<h1>Changelog</h1>', $html);
        $this->assertMatchesRegularExpression('#<h2>\[\d+\.\d+\.\d+\]#', $html);
    }

    public function test_la_pagina_es_indexable_y_lleva_canonical(): void
    {
        $html = $this->get(route('changelog.show'))->assertOk()->getContent();

        $this->assertStringContainsString('name="robots" content="index, follow', $html);
        $this->assertStringContainsString('<link rel="canonical"', $html);
        $this->assertStringContainsString(route('changelog.show'), $html);
    }

    public function test_el_sitemap_incluye_el_historial_pero_no_las_paginas_legales(): void
    {
        $xml = $this->get(route('sitemap'))->assertOk()->getContent();

        $this->assertStringContainsString('<loc>'.route('changelog.show').'</loc>', $xml);
        $this->assertStringNotContainsString('<loc>'.route('terms.show').'</loc>', $xml);
        $this->assertStringNotContainsString('<loc>'.route('data.policy').'</loc>', $xml);
    }

    public function test_las_paginas_legales_llevan_noindex(): void
    {
        $terminos = $this->get(route('terms.show'));

        if ($terminos->status() === 200) {
            $terminos->assertSee('name="robots" content="noindex, nofollow"', false);
        }

        $this->get(route('data.policy'))
            ->assertOk()
            ->assertSee('name="robots" content="noindex, nofollow"', false);
    }
}
