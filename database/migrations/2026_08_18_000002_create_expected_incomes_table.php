<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ingresos mensuales esperados del hogar (salario, arriendos, inversiones…).
     * Son la entrada "ingresos esperados" del cálculo de dinero disponible
     * (ADR-0014): lo que el hogar cuenta recibir, no lo que ya registró.
     */
    public function up(): void
    {
        Schema::create('expected_incomes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained('households')->cascadeOnDelete();
            // Categoría de tipo income (opcional, para reportes futuros).
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('name'); // "Salario Ronaldo", "Arriendo apartamento"
            // DECIMAL(15,2) (ADR-0006). Importe mensual esperado.
            $table->decimal('amount', 15, 2);
            // Día previsto de cobro (1-31). Informativo en Épica 4.
            $table->unsignedTinyInteger('day_of_month')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['household_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expected_incomes');
    }
};
