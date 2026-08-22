<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            // household_id: base del aislamiento multi-hogar (ADR-0005).
            $table->foreignId('household_id')->constrained('households')->cascadeOnDelete();
            // NULL = presupuesto total del período; con valor = presupuesto de esa categoría.
            // Cascade: si se borra la categoría, su presupuesto deja de tener sentido
            // (nullOnDelete lo convertiría por error en un presupuesto total).
            $table->foreignId('category_id')->nullable()->constrained('categories')->cascadeOnDelete();
            // DECIMAL(15,2) (ADR-0006).
            $table->decimal('amount', 15, 2);
            // Enum PHP App\Enums\BudgetPeriod. Épica 4 solo soporta 'monthly'.
            $table->string('period')->default('monthly');
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->timestamps();

            $table->index(['household_id', 'year', 'month']);
            // Un solo presupuesto por hogar/categoría/período. Ojo: MySQL trata los
            // NULL como distintos, así que el presupuesto total (category_id NULL)
            // se protege además en el Form Request.
            $table->unique(['household_id', 'category_id', 'period', 'year', 'month'], 'budgets_unique_period');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('budgets');
    }
};
