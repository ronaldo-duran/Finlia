<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Interruptor «no me muestres más guías» (ADR-0045).
     *
     * Va en `users` y no en `user_tours` porque no habla de ninguna guía en
     * concreto: es la respuesta a «no quiero que la app me enseñe nada», y
     * tiene que poder responderse sin haber visto ni una.
     *
     * Apagarlo NO borra el progreso: volver a encenderlo desde el perfil
     * reanuda donde estaba, en vez de repetir las diez guías de golpe.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('tours_enabled')->default(true)->after('remember_token');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('tours_enabled');
        });
    }
};
