<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Recordatorios del hogar (Épica 9, ADR-0027).
     *
     * SOLO los sueltos del usuario ("obligación anual": tecnomecánica,
     * renovación de pasaporte…). Los de gastos recurrentes, deudas y metas
     * se DERIVAN en vivo de su fuente (next_date, due_day, target_date):
     * duplicarlos aquí sería mantener una copia que caduca.
     *
     * - `frequency` reutiliza App\Enums\Frequency (mensual→anual); NULL =
     *   recordatorio de una sola vez. Al completar uno repetido, la fecha
     *   avanza una frecuencia; el de una sola vez queda `completed`.
     * - `status` persiste únicamente pending|completed: vencido/próximo
     *   se resuelven contra hoy en ReminderStatus::resolve(), así nunca
     *   queda un estado viejo si el cron no corre.
     * - Sin user_id ni scheduled_at: el aviso es del hogar (no de un
     *   miembro) y "cuándo notificar" es derivable de due_date.
     */
    public function up(): void
    {
        Schema::create('reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained('households')->cascadeOnDelete();
            $table->string('title');                        // "Tecnomecánica", "Renovar pasaporte"
            // DECIMAL(15,2) para todo lo monetario (ADR-0006). Nunca FLOAT.
            $table->decimal('amount', 15, 2)->nullable();   // informativo: cuánto cuesta, si se sabe
            $table->date('due_date');                       // puede ser pasada (= vencido)
            $table->string('frequency')->nullable();        // enum App\Enums\Frequency (mensual→anual); NULL = una sola vez
            $table->string('status')->default('pending');   // enum App\Enums\ReminderStatus (solo pending|completed se persisten)
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['household_id', 'status', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminders');
    }
};
