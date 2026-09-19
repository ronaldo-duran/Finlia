<?php

declare(strict_types=1);

namespace Finlia\Ee;

use Illuminate\Support\ServiceProvider;

/**
 * Punto único de contacto del núcleo con `ee/` (ADR-0042, ee/README.md).
 *
 * Se registra CONDICIONALMENTE desde `AppServiceProvider::register()` con
 * `class_exists(...)`, de modo que borrar el directorio `ee/` deja la app
 * funcionando: la clase no existe, el registro se salta y el núcleo AGPL
 * corre solo, sirviendo todo lo gratuito.
 *
 * Hoy este provider no registra funciones Premium — la Épica 12 v0.39
 * pone rieles, no funciones. Cuando llegue una (chat IA, PDF Premium,
 * autoconocimiento avanzado) se ata aquí, no en el núcleo.
 */
final class EeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Sin features Premium activadas por ahora.
    }

    public function boot(): void
    {
        // Sin bindings ni observers Premium por ahora.
    }
}
