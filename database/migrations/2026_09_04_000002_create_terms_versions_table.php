<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Versiones de los términos y condiciones (Plan 03).
     *
     * Cada fila es INMUTABLE: contiene el texto completo de ESA versión y
     * nunca se edita — cambiar los términos significa publicar una versión
     * nueva. Así la aceptación registrada siempre referencia el texto
     * exacto que el usuario vio (valor probatorio, Ley 1581).
     *
     * No lleva soft deletes a propósito: borrar una versión aceptada
     * rompería la prueba de consentimiento (además lo impide el RESTRICT
     * de user_terms_acceptances).
     */
    public function up(): void
    {
        Schema::create('terms_versions', function (Blueprint $table) {
            $table->id();
            $table->string('version', 30)->unique();
            $table->string('title', 150);
            $table->longText('content');
            $table->text('change_summary')->nullable();
            $table->timestamp('published_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('terms_versions');
    }
};
