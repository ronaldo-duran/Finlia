<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\TourService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pone a disposición del layout la guía de la pantalla actual (ADR-0045).
 *
 * Aquí vive la regla de «poco invasiva», que es de la petición y no del
 * contenido:
 *
 *   · una guía arranca sola solo la PRIMERA vez que se entra a su pantalla;
 *   · como mucho UNA por sesión — nunca dos globos seguidos en un paseo
 *     por la app;
 *   · nunca fuera de un GET que devuelva página (un POST que redirige no
 *     debe quemar el cupo de la sesión).
 *
 * El parámetro `?guia=` salta las tres: lo escribe el catálogo del perfil, y
 * pedir una guía a mano es justo lo contrario de que te la impongan.
 */
class ShareActiveTour
{
    /** Cupo de la sesión: ya se mostró una guía automática. */
    private const SESSION_KEY = 'tour.shown';

    public function __construct(private readonly TourService $tours) {}

    public function handle(Request $request, Closure $next): Response
    {
        // Se limpia SIEMPRE antes de decidir. `View::share` vive en el
        // contenedor, no en la petición: sin esto, la guía de la pantalla
        // anterior se arrastraría a la siguiente y aparecería «Guía de esta
        // pantalla» en pantallas que no tienen ninguna. No se nota con
        // php-fpm (proceso nuevo por petición), sí en los tests y en
        // cualquier runtime persistente.
        View::share('finliaTour', null);

        $user = $request->user();

        // Sin sesión no hay progreso que consultar, y una guía en medio de un
        // POST no tendría dónde pintarse.
        if ($user === null || ! $request->isMethod('GET')) {
            return $next($request);
        }

        // Guía pedida a mano desde el catálogo del perfil. Se comprueba contra
        // el registro: una clave inventada en la URL simplemente no abre nada.
        $requested = $request->query('guia');
        $requested = is_string($requested) && $this->tours->find($requested) !== null
            ? $requested
            : null;

        $key = $requested ?? $this->tours->keyForRoute($request->route()?->getName());

        if ($key === null) {
            return $next($request);
        }

        $auto = $requested === null
            && ! $request->session()->get(self::SESSION_KEY, false)
            && $this->tours->shouldAutoStart($user, $key);

        View::share('finliaTour', [
            'payload' => $this->tours->payloadFor($user, $key),
            // 'auto'  → arranca sola con los pasos pendientes.
            // 'open'  → la pidió la persona: arranca completa.
            // null    → no arranca; queda disponible en el menú del avatar.
            'start' => $requested !== null ? 'open' : ($auto ? 'auto' : null),
        ]);

        $response = $next($request);

        // El cupo se quema DESPUÉS y solo si de verdad salió una página: si el
        // controlador acabó redirigiendo o dando un 404, la guía no llegó a
        // verse y sería injusto gastarle la sesión al usuario.
        if ($auto && $response->getStatusCode() === 200) {
            $request->session()->put(self::SESSION_KEY, true);
        }

        return $response;
    }
}
