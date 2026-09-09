<?php

namespace Tests\Feature\Marketing;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sitio público (finlia.online).
 *
 * Lo que se fija aquí no es el diseño —eso cambia— sino lo que rompe en
 * silencio: los metadatos que deciden cómo se ve el enlace al compartirlo y
 * cómo lo describe un buscador. Un `og:image` que desaparece no da error en
 * ninguna parte; simplemente el enlace deja de tener vista previa.
 */
class LandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_landing_responde_sin_sesion(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('¿Cuánto puedes gastar hoy', false)
            ->assertSee('Finanzas personales y familiares', false);
    }

    public function test_la_landing_lleva_los_metadatos_para_compartir(): void
    {
        $html = $this->get(route('home'))->assertOk()->getContent();

        foreach ([
            '<meta name="description"',
            '<link rel="canonical"',
            'property="og:title"',
            'property="og:image"',
            'property="og:image:width" content="1200"',
            'name="twitter:card" content="summary_large_image"',
            'name="robots" content="index, follow',
        ] as $fragmento) {
            $this->assertStringContainsString($fragmento, $html, "Falta en la landing: {$fragmento}");
        }
    }

    public function test_los_datos_estructurados_son_json_valido_y_describen_la_aplicacion(): void
    {
        $html = $this->get(route('home'))->assertOk()->getContent();

        $this->assertSame(1, preg_match(
            '#<script type="application/ld\+json">\s*(\{.*?\})\s*</script>#s',
            $html,
            $m
        ), 'No hay un bloque JSON-LD en la landing.');

        $datos = json_decode($m[1], true, flags: JSON_THROW_ON_ERROR);
        $tipos = array_column($datos['@graph'], '@type');

        $this->assertContains('SoftwareApplication', $tipos);
        $this->assertContains('FAQPage', $tipos);
    }

    /**
     * Las preguntas del acordeón y las del esquema salen del mismo array.
     * Si alguien duplica la lista, el buscador acabaría prometiendo una
     * respuesta que la página ya no da: esto lo detecta.
     */
    public function test_cada_pregunta_del_esquema_aparece_en_la_pagina(): void
    {
        $html = $this->get(route('home'))->assertOk()->getContent();

        preg_match('#<script type="application/ld\+json">\s*(\{.*?\})\s*</script>#s', $html, $m);
        $datos = json_decode($m[1], true, flags: JSON_THROW_ON_ERROR);

        $faq = collect($datos['@graph'])->firstWhere('@type', 'FAQPage');
        $this->assertNotEmpty($faq['mainEntity']);

        foreach ($faq['mainEntity'] as $pregunta) {
            $this->assertStringContainsString(
                e($pregunta['name']),
                $html,
                "La pregunta «{$pregunta['name']}» está en el esquema pero no en la página."
            );
        }
    }

    public function test_el_sitemap_lista_las_paginas_publicas(): void
    {
        $respuesta = $this->get(route('sitemap'))->assertOk();

        $this->assertStringStartsWith('application/xml', $respuesta->headers->get('content-type'));

        $xml = $respuesta->getContent();
        $this->assertStringContainsString('<loc>'.route('home').'</loc>', $xml);
        $this->assertStringContainsString('<loc>'.route('terms.show').'</loc>', $xml);
    }

    public function test_llms_txt_se_sirve_como_texto_plano(): void
    {
        $respuesta = $this->get(route('llms'))->assertOk();

        $this->assertStringStartsWith('text/plain', $respuesta->headers->get('content-type'));
        $this->assertStringContainsString('# Finlia', $respuesta->getContent());
        // El "qué NO hace" evita que un asistente afirme que Finlia se conecta
        // al banco o da asesoría financiera.
        $this->assertStringContainsString('No se conecta a bancos', $respuesta->getContent());
    }

    public function test_robots_permite_el_sitio_publico_y_bloquea_la_aplicacion(): void
    {
        config(['finlia.domains.app' => 'app.finlia.test']);

        $publico = $this->get('http://finlia.test/robots.txt')->assertOk()->getContent();
        $this->assertStringContainsString('Allow: /', $publico);
        $this->assertStringContainsString('Sitemap:', $publico);

        $aplicacion = $this->get('http://app.finlia.test/robots.txt')->assertOk()->getContent();
        $this->assertStringContainsString('Disallow: /', $aplicacion);
        $this->assertStringNotContainsString('Sitemap:', $aplicacion);
    }

    public function test_la_tarjeta_para_compartir_no_se_indexa(): void
    {
        $this->get(route('og-preview'))
            ->assertOk()
            ->assertSee('name="robots" content="noindex, nofollow"', false);
    }
}
