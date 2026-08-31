<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bloquea la app si el usuario no aceptó la versión vigente de los
 * términos (Plan 03, ADR-0031).
 *
 * Vive en el grupo de rutas con 'auth' + 'verified', después de ellos:
 * cubre el registro nuevo y el cambio de términos con un solo mecanismo.
 * Sin versión publicada no redirige (fail-open): no hay nada que aceptar.
 */
class EnsureTermsAccepted
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && ! $user->hasAcceptedCurrentTerms()) {
            return redirect()->route('terms.accept');
        }

        return $next($request);
    }
}
