<?php

use App\Http\Middleware\EnsureAccountActive;
use App\Http\Middleware\EnsureTermsAccepted;
use App\Http\Middleware\ShareActiveTour;
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
        $middleware->web(append: [
            AuthenticateSession::class,
        ]);

        $middleware->alias([
            'terms.current' => EnsureTermsAccepted::class,
            'account.active' => EnsureAccountActive::class,
            'tour' => ShareActiveTour::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
