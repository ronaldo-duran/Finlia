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
        Schema::create('incomes', function (Blueprint $table) {
            $table->id();
            // household_id denormalizado para aislamiento (ADR-0005).
            $table->foreignId('household_id')->constrained('households')->cascadeOnDelete();
            // Usuario que registró el ingreso.
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // Cuenta afectada (incrementa saldo).
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            // Categoría (tipo income). Nullable: si se borra la categoría custom, queda libre.
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            // Monto siempre positivo. DECIMAL(15,2) (ADR-0006).
            $table->decimal('amount', 15, 2);
            $table->date('date');
            $table->string('description')->nullable();
            $table->text('notes')->nullable();
            $table->string('source')->nullable(); // salario, freelance, etc.
            $table->timestamps();
            $table->softDeletes();

            // Alimentan el dashboard y los filtros (ver índices recomendados en DATA_MODEL).
            $table->index(['household_id', 'date']);
            $table->index(['household_id', 'category_id']);
            $table->index('account_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('incomes');
    }
};
