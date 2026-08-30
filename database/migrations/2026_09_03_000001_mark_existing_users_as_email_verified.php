<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Plan 01: los usuarios ya registrados quedan verificados de oficio.
 *
 * Antes de esta migración no existía verificación de correo (nadie verificó
 * porque nadie pudo), y forzarles el flujo a una base de usuarios de
 * desarrollo no aporta. A partir de aquí, TODO usuario con
 * `email_verified_at` NULL es un registro posterior a este despliegue que
 * aún no confirmó su correo — y por construcción no ha podido crear datos
 * (el middleware 'verified' se lo impide). Esa es la invariante que hace
 * segura la reclaim del anti-squatting en el registro.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->whereNull('email_verified_at')
            ->update(['email_verified_at' => now()]);
    }

    public function down(): void
    {
        // Irreversible: no sabemos quiénes habían "verificado de verdad"
        // (nadie) y quiénes fueron marcados por esta migración. Volver
        // atrás dejaría a todos los usuarios fuera de la app.
    }
};
