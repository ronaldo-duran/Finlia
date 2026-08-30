<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Épica 9 (ADR-0018): la generación automática de gastos requiere el
     * Scheduler, y este es el momento en que existe. `auto_generate` es un
     * opt-in explícito del usuario por obligación: en false (por defecto)
     * todo pago sigue siendo una acción manual ("Marcar pagado").
     */
    public function up(): void
    {
        Schema::table('recurring_expenses', function (Blueprint $table) {
            $table->boolean('auto_generate')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('recurring_expenses', function (Blueprint $table) {
            $table->dropColumn('auto_generate');
        });
    }
};
