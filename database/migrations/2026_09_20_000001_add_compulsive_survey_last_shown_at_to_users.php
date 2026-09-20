<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ronda de recordatorio del árbol de decisión (Épica 12).
 *
 * `compulsive_survey_last_shown_at` guarda cuándo se le mostró el modal por
 * última vez a este usuario. Sirve para respetar la regla de "un modal por
 * día", vive en `users` (y no en `compulsive_survey_responses`) porque el
 * usuario puede descartar el modal sin dejar respuesta, y aun así debemos
 * saber que ya lo vio hoy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('compulsive_survey_last_shown_at')->nullable()->after('tours_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('compulsive_survey_last_shown_at');
        });
    }
};
