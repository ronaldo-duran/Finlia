<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Épica 9: interruptor de recordatorios del hogar ("Permitir
     * activar/desactivar recordatorios"). Es una preferencia del hogar,
     * no del usuario: las obligaciones son compartidas y el aviso se
     * muestra a todos los miembros.
     */
    public function up(): void
    {
        Schema::table('households', function (Blueprint $table) {
            $table->boolean('reminders_enabled')->default(true)->after('timezone');
        });
    }

    public function down(): void
    {
        Schema::table('households', function (Blueprint $table) {
            $table->dropColumn('reminders_enabled');
        });
    }
};
