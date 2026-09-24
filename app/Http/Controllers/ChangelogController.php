<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;
use League\CommonMark\CommonMarkConverter;

/**
 * Página pública de historial de versiones (renderiza CHANGELOG.md).
 *
 * El archivo es la fuente única de verdad — se edita con `/update-changelog`
 * al cerrar cada entrega — y esta ruta lo pone a la vista de cualquiera:
 * enseñar lo que Finlia va entregando genera confianza y ayuda a que un
 * buscador entienda que la app está viva.
 *
 * El resultado se cachea porque el archivo solo cambia en un merge a main.
 */
class ChangelogController extends Controller
{
    private const CACHE_KEY = 'marketing.changelog.html';

    public function __construct(private readonly Cache $cache) {}

    public function show(): View
    {
        $ruta = base_path('CHANGELOG.md');

        $mtime = File::exists($ruta) ? File::lastModified($ruta) : 0;

        $html = $this->cache->remember(
            self::CACHE_KEY.'.'.$mtime,
            now()->addDay(),
            fn (): string => $this->render($ruta)
        );

        return view('marketing.changelog', ['html' => $html]);
    }

    private function render(string $ruta): string
    {
        if (! File::exists($ruta)) {
            return '';
        }

        $conversor = new CommonMarkConverter([
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
        ]);

        return (string) $conversor->convert(File::get($ruta));
    }
}
