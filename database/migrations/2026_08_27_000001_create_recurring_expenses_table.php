<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Gastos recurrentes y obligaciones futuras del hogar (Épica 5): SOAT,
     * arriendo, suscripciones, matrícula… Cada uno conoce su próxima fecha
     * y alimenta el cálculo de dinero disponible (seams de ADR-0014).
     *
     * Nota: no hay columna auto_generate (sí prevista en DATA_MODEL como
     * opcional): la generación automática de gastos exige el Scheduler de la
     * Épica 9. Aquí el pago se registra a mano ("Marcar pagado").
     */
    public function up(): void
    {
        Schema::create('recurring_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained('households')->cascadeOnDelete();
            // Categoría de tipo expense (opcional).
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            // Cuenta con la que se paga normalmente (opcional; si existe,
            // "Marcar pagado" registra el gasto real sobre ella).
            $table->foreignId('account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->string('name'); // "SOAT", "Arriendo", "Netflix"…
            // DECIMAL(15,2) (ADR-0006). Monto estimado por ocurrencia.
            $table->decimal('amount', 15, 2);
            $table->string('frequency'); // enum App\Enums\Frequency
            // Cada N días. Solo para frecuencia personalizada.
            $table->unsignedSmallInteger('frequency_interval')->nullable();
            // Próxima fecha de pago. Puede ser pasada (= obligación vencida).
            $table->date('next_date');
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['household_id', 'is_active']);
            $table->index(['household_id', 'next_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recurring_expenses');
    }
};
