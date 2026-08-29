<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Refinanciaciones de una deuda (Épica 6): cambian las condiciones y
     * fijan una nueva LÍNEA BASE del saldo (ADR-0020). A partir de
     * `start_date`, el saldo se calcula desde `refinanced_balance` y solo
     * cuentan los pagos posteriores.
     */
    public function up(): void
    {
        Schema::create('debt_refinancings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('debt_id')->constrained('debts')->cascadeOnDelete();
            $table->foreignId('household_id')->constrained('households')->cascadeOnDelete();
            // Saldo que queda refinanciado: nueva línea base.
            $table->decimal('refinanced_balance', 15, 2);
            $table->decimal('interest_rate', 6, 3)->nullable(); // nueva tasa % anual
            $table->unsignedSmallInteger('term_months')->nullable(); // nuevo plazo
            $table->decimal('installment', 15, 2)->nullable();  // nueva cuota
            $table->date('start_date');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['debt_id', 'start_date']);
            $table->index('household_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('debt_refinancings');
    }
};
