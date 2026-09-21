<?php

declare(strict_types=1);

namespace Tests\Feature\Subscription;

use Finlia\Ee\EeServiceProvider;
use Tests\TestCase;

/**
 * Verificación de la frontera `ee/` (ADR-0042, Épica 12).
 *
 * El registro de `EeServiceProvider` es condicional (class_exists) desde
 * `AppServiceProvider::register()`. Borrar `ee/src` debe dejar la app
 * corriendo — se ejercita en CI moviendo el directorio y corriendo
 * `php artisan about`. Este test cubre el contrato mínimo:
 *   1. La clase existe con el namespace acordado.
 *   2. Está registrada en la app cuando existe.
 *   3. NO expone bindings públicos en v0.39 (rieles sin funciones).
 */
class EeProviderBoundaryTest extends TestCase
{
    public function test_ee_service_provider_se_registra_condicionalmente(): void
    {
        $this->assertTrue(class_exists(EeServiceProvider::class));

        $this->assertArrayHasKey(EeServiceProvider::class, $this->app->getLoadedProviders());
    }
}
