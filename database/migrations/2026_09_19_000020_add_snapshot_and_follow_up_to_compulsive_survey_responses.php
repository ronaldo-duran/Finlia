<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshot demográfico y seguimiento (Épica 12, v0.39).
 *
 * - `age_years` y `gender` congelan los datos del usuario AL MOMENTO de
 *   responder el árbol. La edad se deriva de `birth_date` pero no se puede
 *   recalcular fielmente en el futuro (el usuario puede corregir su fecha
 *   de nacimiento, y en cualquier caso queremos correlacionar comportamiento
 *   con "cómo estabas cuando lo compraste").
 *
 * - `follow_up_due_at` marca cuándo volver a preguntarle al usuario cómo se
 *   siente sobre esa compra. Se agenda por defecto a 30 días del registro
 *   (`CompulsiveSurveyService`). El resto de columnas guardan la respuesta:
 *   `mood_after`, `follow_up_regret` (¿te arrepientes?) y una nota libre.
 *   Nulables porque el seguimiento no obliga.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('compulsive_survey_responses', function (Blueprint $table): void {
            $table->unsignedTinyInteger('age_years')->nullable()->after('trigger');
            $table->string('gender', 20)->nullable()->after('age_years');

            $table->timestamp('follow_up_due_at')->nullable()->after('gender');
            $table->timestamp('follow_up_answered_at')->nullable()->after('follow_up_due_at');
            $table->unsignedTinyInteger('mood_after')->nullable()->after('follow_up_answered_at');
            $table->boolean('follow_up_regret')->nullable()->after('mood_after');
            $table->string('follow_up_note', 500)->nullable()->after('follow_up_regret');

            $table->index(['household_id', 'follow_up_due_at']);
        });
    }

    public function down(): void
    {
        Schema::table('compulsive_survey_responses', function (Blueprint $table): void {
            $table->dropIndex(['household_id', 'follow_up_due_at']);
            $table->dropColumn([
                'age_years',
                'gender',
                'follow_up_due_at',
                'follow_up_answered_at',
                'mood_after',
                'follow_up_regret',
                'follow_up_note',
            ]);
        });
    }
};
