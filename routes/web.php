<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas web — Finami
|--------------------------------------------------------------------------
| Auth nativa por sesiones. UI/URLs en español. Las rutas privadas se
| agrupan bajo middleware 'auth'.
*/

// Raíz: redirige según sesión.
Route::get('/', function () {
    return redirect()->route(auth()->check() ? 'dashboard' : 'login');
})->name('home');

// ---- Rutas públicas (solo invitados) ----
Route::middleware('guest')->group(function () {
    // Registro
    Route::get('registro', [RegisteredUserController::class, 'create'])
        ->name('register');
    Route::post('registro', [RegisteredUserController::class, 'store'])
        ->middleware('throttle:5,1');

    // Inicio de sesión
    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:5,1');

    // Recuperación de contraseña
    Route::get('recuperar-contrasena', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');
    Route::post('recuperar-contrasena', [PasswordResetLinkController::class, 'store'])
        ->name('password.email')
        ->middleware('throttle:5,1');

    Route::get('restablecer-contrasena/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');
    Route::post('restablecer-contrasena', [NewPasswordController::class, 'store'])
        ->name('password.update')
        ->middleware('throttle:5,1');
});

// ---- Rutas privadas (requieren sesión) ----
Route::middleware('auth')->group(function () {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');

    Route::get('dashboard', DashboardController::class)
        ->name('dashboard');
});
