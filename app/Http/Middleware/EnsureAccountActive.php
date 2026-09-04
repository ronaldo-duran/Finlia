<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bloquea la app si la cuenta está en proceso de eliminación (Plan 05,
 * ADR-0033). Permite el acceso a la página de suspensión, la reactivación
 * y el cierre de sesión; todo lo demás redirige a /cuenta/suspendida.
 */
class EnsureAccountActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && $user->isSuspended()) {
            return redirect()->route('account.suspended');
        }

        return $next($request);
    }
}
