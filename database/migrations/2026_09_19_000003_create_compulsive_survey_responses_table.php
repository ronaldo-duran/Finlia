<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Respuestas al árbol de decisión de compras (Épica 12, v0.39).
 *
 * Se dispara con probabilidad (backend) al registrar un gasto candidato.
 * La data queda para autoconocimiento (feature Premium futura) y para
 * dataset de ML. Sin edición (una vez respondida, se queda) — de ahí
 * `updated_at` sin usarse.
 *
 * Aislamiento: `household_id` como en todas las tablas financieras
 * (docs/SECURITY.md §1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compulsive_survey_responses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('household_id')
                ->constrained('households')
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->foreignId('expense_id')
                ->constrained('expenses')
                ->cascadeOnDelete();

            $table->string('planned', 10);   // App\Enums\CompulsivePlanned
            $table->string('kind', 15);      // App\Enums\CompulsiveKind
            $table->unsignedTinyInteger('mood');   // 1..5 (App\Enums\CompulsiveMood)
            $table->string('trigger', 20);   // App\Enums\CompulsiveTrigger

            $table->timestamps();

            // Conteo mensual por hogar (freemium: 5/mes).
            $table->index(['household_id', 'created_at']);
            // Una encuesta por gasto: nadie se contesta dos veces la misma.
            $table->unique('expense_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compulsive_survey_responses');
    }
};
