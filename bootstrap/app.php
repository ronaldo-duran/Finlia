<?php

use App\Http\Middleware\EnsureAccountActive;
use App\Http\Middleware\EnsureTermsAccepted;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\AuthenticateSession;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // En Laravel 11+ Auth::logoutOtherDevices() solo re-hashea la
        // contraseña: son las sesiones las que se comparan contra ese hash
        // en cada request (Plan 02, ADR-0030). Sin este middleware, rotar la
        // contraseña no revocaría las sesiones de otros dispositivos.
        $middleware->web(append: [
            AuthenticateSession::class,
        ]);

        // Re-aceptación obligatoria de términos (Plan 03, ADR-0031). Se
        // aplica al grupo de rutas privadas con sesión + correo verificado.
        // Bloqueo de cuentas en suspensión (Plan 05, ADR-0033). Se aplica al
        // mismo grupo para que los rutas de suspensión/reactivación queden fuera.
        $middleware->alias([
            'terms.current' => EnsureTermsAccepted::class,
            'account.active' => EnsureAccountActive::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
