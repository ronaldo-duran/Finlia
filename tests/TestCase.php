<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Los feature tests no deben depender de los assets compilados por Vite:
     * sin esto, toda vista con @vite lanza ViteManifestNotFoundException en
     * entornos sin `npm run build` (p. ej. el runner de CI).
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }
}
