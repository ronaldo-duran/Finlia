<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Avisos que el usuario ya ha leído y no quiere volver a ver completos
     * (ADR-0024).
     *
     * Tabla por CLAVE en lugar de una columna por aviso: metas de ahorro y
     * reportes traerán advertencias parecidas, y una columna nueva por cada
     * una acabaría en media docena de `*_ack_at` en `users`.
     *
     * Es una preferencia del USUARIO, no del hogar: dos miembros del mismo
     * hogar leen (o no) el aviso por separado.
     */
    public function up(): void
    {
        Schema::create('user_acknowledgements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // Valor de App\Enums\AcknowledgementKey. Se valida contra el enum
            // antes de insertar: la clave nunca llega libre desde la petición.
            $table->string('key', 60);
            $table->timestamp('acknowledged_at');
            $table->timestamps();

            // Un acuse por usuario y aviso; también acota el crecimiento.
            $table->unique(['user_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_acknowledgements');
    }
};
