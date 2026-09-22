<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Guías de pantalla que el usuario ya vio (ADR-0045).
     *
     * Una fila por usuario y guía, con la VERSIÓN vista. La versión es lo que
     * permite presentar novedades sin repetir lo ya sabido: al subir la
     * versión de una guía en config/tours.php, quien la tenía vista en la
     * anterior solo recibe los pasos nacidos después.
     *
     * Tabla por clave y no una columna por guía en `users`: hay diez guías
     * hoy y cada funcionalidad nueva trae la suya; una columna por cada una
     * acabaría en una tabla `users` imposible de leer (mismo criterio que
     * `user_acknowledgements`, ADR-0024).
     *
     * Es del USUARIO, no del hogar: dos miembros del mismo hogar aprenden la
     * app por separado. Por eso vive fuera del multi-tenant.
     */
    public function up(): void
    {
        Schema::create('user_tours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('key', 40);
            $table->unsignedSmallInteger('version');
            $table->string('status', 12);
            $table->timestamp('seen_at');
            $table->timestamps();

            $table->unique(['user_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_tours');
    }
};
