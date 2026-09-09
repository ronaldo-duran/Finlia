<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;

/**
 * Sitio público de Finlia (finlia.online).
 *
 * Separado de la aplicación a propósito: aquí no hay sesión, ni hogar activo,
 * ni datos financieros — es la puerta de entrada para quien todavía no tiene
 * cuenta, y lo único que comparte con la app es el sistema de diseño.
 *
 * Para añadir una página nueva (precios, testimonios…): un método aquí, su
 * ruta en el grupo de marketing y una entrada en PAGINAS. El sitemap y el
 * índice para IAs se actualizan solos.
 */
class MarketingController extends Controller
{
    /**
     * Páginas indexables del sitio: nombre de ruta => prioridad en el sitemap.
     *
     * @var array<string, string>
     */
    private const PAGINAS = [
        'home' => '1.0',
        'data.policy' => '0.5',
        'terms.show' => '0.5',
    ];

    public function home(): View
    {
        return view('marketing.home');
    }

    /**
     * Tarjeta 1200×630 para las vistas previas al compartir el enlace.
     *
     * Es una página real y no una imagen a mano para que se regenere con
     * `npm run screenshots` cuando cambie la marca o el mensaje. Lleva
     * noindex: no es contenido, es materia prima de una captura.
     */
    public function ogPreview(): View
    {
        return view('marketing.og-preview');
    }

    /**
     * robots.txt dinámico: depende del host.
     *
     * El sitio público se rastrea entero; la aplicación no se rastrea en
     * absoluto. Todo lo que hay en app.finlia.online está tras sesión, así
     * que un buscador solo indexaría pantallas de login — ruido, y con el
     * nombre del dominio de la app expuesto sin necesidad.
     */
    public function robots(Request $request): Response
    {
        $esMarketing = $this->esHostDeMarketing($request);

        $cuerpo = $esMarketing
            ? implode("\n", [
                'User-agent: *',
                'Allow: /',
                '',
                '# Índice legible para asistentes de IA (llmstxt.org)',
                '# '.route('llms'),
                '',
                'Sitemap: '.route('sitemap'),
                '',
            ])
            : implode("\n", [
                '# Aplicación privada: nada que indexar aquí.',
                'User-agent: *',
                'Disallow: /',
                '',
            ]);

        return response($cuerpo, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    /**
     * Sitemap XML generado desde PAGINAS.
     */
    public function sitemap(): Response
    {
        $urls = collect(self::PAGINAS)
            ->filter(fn (string $prioridad, string $ruta) => Route::has($ruta))
            ->map(fn (string $prioridad, string $ruta) => [
                'loc' => route($ruta),
                'priority' => $prioridad,
            ]);

        return response()
            ->view('marketing.sitemap', ['urls' => $urls])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    /**
     * /llms.txt — resumen en texto plano para asistentes de IA.
     *
     * Convención emergente (llmstxt.org): un buscador con IA que aterrice
     * aquí obtiene qué es Finlia y qué hace sin tener que interpretar el
     * HTML de la landing. Cuesta un fichero y evita que nos describan mal.
     */
    public function llms(): Response
    {
        return response()
            ->view('marketing.llms')
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    private function esHostDeMarketing(Request $request): bool
    {
        $dominioApp = config('finlia.domains.app');

        // Sin dominios configurados (local) todo es marketing: es el único
        // host que hay, y bloquearlo impediría probar el rastreo.
        if ($dominioApp === null) {
            return true;
        }

        return $request->getHost() !== $dominioApp;
    }
}
