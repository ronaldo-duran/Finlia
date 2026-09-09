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
}
