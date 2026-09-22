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
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignId('account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->string('name');
            $table->decimal('amount', 15, 2);
            $table->string('frequency');
            $table->unsignedSmallInteger('frequency_interval')->nullable();
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
