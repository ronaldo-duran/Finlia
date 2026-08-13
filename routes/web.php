<?php

use App\Http\Controllers\ActiveHouseholdController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HouseholdController;
use App\Http\Controllers\HouseholdInvitationController;
use App\Http\Controllers\HouseholdMemberController;
use App\Http\Controllers\InvitationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas web — Finlia
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

    // ---- Hogares (Épica 2) ----
    Route::get('hogares', [HouseholdController::class, 'index'])->name('households.index');
    Route::get('hogares/crear', [HouseholdController::class, 'create'])->name('households.create');
    Route::post('hogares', [HouseholdController::class, 'store'])->name('households.store');
    Route::get('hogares/{household}', [HouseholdController::class, 'show'])->name('households.show');
    Route::get('hogares/{household}/editar', [HouseholdController::class, 'edit'])->name('households.edit');
    Route::put('hogares/{household}', [HouseholdController::class, 'update'])->name('households.update');
    Route::delete('hogares/{household}', [HouseholdController::class, 'destroy'])->name('households.destroy');
    Route::post('hogares/{household}/activar', ActiveHouseholdController::class)->name('households.activate');

    // Miembros (acciones dentro de un hogar)
    Route::delete('hogares/{household}/miembros/{user}', [HouseholdMemberController::class, 'destroy'])
        ->name('households.members.destroy');

    // Invitaciones: enviar / revocar
    Route::post('hogares/{household}/invitaciones', [HouseholdInvitationController::class, 'store'])
        ->name('households.invitations.store');
    Route::delete('hogares/{household}/invitaciones/{invitation}', [HouseholdInvitationController::class, 'destroy'])
        ->name('households.invitations.destroy');

    // Aceptar invitación por enlace (token hasheado en BD)
    Route::get('invitaciones/{token}', [InvitationController::class, 'show'])
        ->name('invitations.show')
        ->middleware('throttle:10,1');
    Route::post('invitaciones/{token}', [InvitationController::class, 'accept'])
        ->name('invitations.accept')
        ->middleware('throttle:10,1');
});
