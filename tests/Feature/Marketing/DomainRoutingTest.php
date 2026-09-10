<?php

namespace Tests\Feature\Marketing;

use Tests\TestCase;

/**
 * Reparto en dos hosts: finlia.online (sitio) y app.finlia.online (aplicación).
 *
 * Las rutas se registran al arrancar leyendo config('finlia.domains.*'), así
 * que no basta con cambiar la configuración dentro del test: hay que fijar el
 * entorno y recrear la aplicación para que el router se vuelva a construir.
 *
 * Sin base de datos a propósito — aquí solo se comprueba enrutado, y
 * refrescar la aplicación en mitad de una transacción de RefreshDatabase se
 * lleva por delante el rollback.
 */
class DomainRoutingTest extends TestCase
{
    private const MARKETING = 'finlia.test';

    private const APP = 'app.finlia.test';

    protected function setUp(): void
    {
        parent::setUp();

        putenv('FINLIA_MARKETING_DOMAIN='.self::MARKETING);
        putenv('FINLIA_APP_DOMAIN='.self::APP);

        $this->refreshApplication();

        // refreshApplication() rehace el contenedor y se lleva por delante el
        // withoutVite() de Tests\TestCase::setUp(). Sin volver a ponerlo, la
        // vista falla con ViteManifestNotFoundException en cualquier entorno
        // sin `npm run build` — que es justo el job de PHP del CI.
        $this->withoutVite();
    }

    protected function tearDown(): void
    {
        putenv('FINLIA_MARKETING_DOMAIN');
        putenv('FINLIA_APP_DOMAIN');

        parent::tearDown();
    }

    public function test_el_host_del_sitio_sirve_la_landing(): void
    {
        $this->get('http://'.self::MARKETING.'/')
            ->assertOk()
            ->assertSee('¿Cuánto puedes gastar hoy', false);
    }

    /**
     * En el host de la aplicación no hay landing que enseñar: quien llega sin
     * sesión va al login. Es la regla que, registrada sin dominio, sustituía a
     * la landing por colisión de rutas.
     */
    public function test_el_host_de_la_aplicacion_redirige_al_login(): void
    {
        $this->get('http://'.self::APP.'/')
            ->assertRedirect(route('login'));
    }

    public function test_la_landing_no_responde_en_el_host_de_la_aplicacion(): void
    {
        $this->get('http://'.self::APP.'/llms.txt')->assertNotFound();
        $this->get('http://'.self::APP.'/sitemap.xml')->assertNotFound();
    }

    public function test_las_urls_publicas_apuntan_al_host_del_sitio(): void
    {
        $this->assertStringContainsString(self::MARKETING, route('home'));
        $this->assertStringNotContainsString(self::APP, route('home'));
    }

    /**
     * La aplicación NO debe responder en el host del sitio.
     *
     * Si lo hace, los enlaces de la landing se quedan en el host equivocado,
     * un buscador puede indexar la pantalla de login y —lo caro— la PWA se
     * puede instalar desde el dominio del sitio y queda atada a él.
     */
    public function test_la_aplicacion_no_responde_en_el_host_del_sitio(): void
    {
        $this->get('http://'.self::MARKETING.'/login')->assertNotFound();
        $this->get('http://'.self::MARKETING.'/dashboard')->assertNotFound();
        $this->get('http://'.self::MARKETING.'/manifest.webmanifest')->assertNotFound();
    }

    public function test_la_aplicacion_responde_en_su_propio_host(): void
    {
        $this->get('http://'.self::APP.'/login')->assertOk();
        $this->get('http://'.self::APP.'/manifest.webmanifest')->assertOk();
    }

    /**
     * Las páginas legales son al revés: viven en el sitio, porque son
     * públicas, indexables y entran en el sitemap.
     */
    public function test_las_paginas_legales_viven_en_el_sitio(): void
    {
        $this->get('http://'.self::MARKETING.'/datos')->assertOk();
        $this->get('http://'.self::APP.'/datos')->assertNotFound();
    }

    /**
     * Lo que reportó el fallo: desde la landing, "Entrar" mandaba a
     * finlia.online/login en vez de al subdominio de la aplicación.
     */
    public function test_los_enlaces_de_entrar_y_registrarse_apuntan_a_la_aplicacion(): void
    {
        foreach (['login', 'register'] as $ruta) {
            $this->assertStringContainsString(self::APP, route($ruta), "route('{$ruta}') no apunta al host de la aplicación.");
        }

        $this->assertStringContainsString(self::MARKETING, route('terms.show'));
        $this->assertStringContainsString(self::MARKETING, route('data.policy'));
    }
}
