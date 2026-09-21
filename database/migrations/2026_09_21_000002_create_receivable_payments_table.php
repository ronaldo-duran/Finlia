<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cobros registrados contra una cuenta por cobrar (Épica 15). Son la
     * fuente de verdad del saldo: `receivables.current_balance` se
     * recalcula a partir de ellos (ADR-0020 espejo).
     *
     * Cuando el cobro entra a una cuenta del hogar, `income_id` enlaza el
     * ingreso real que movió el saldo de esa cuenta (ADR-0021 espejo),
     * para que el dinero no aparezca ni de más ni de menos.
     */
    public function up(): void
    {
        Schema::create('receivable_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('receivable_id')->constrained('receivables')->cascadeOnDelete();
            // Denormalizado a propósito: permite acotar por hogar sin join
            // (aislamiento multi-hogar, amenaza #1).
            $table->foreignId('household_id')->constrained('households')->cascadeOnDelete();
            $table->foreignId('account_id')->nullable()->constrained('accounts')->nullOnDelete();
            // Ingreso generado, si el cobro entró a una cuenta del hogar.
            // nullOnDelete: borrar el movimiento no borra el historial.
            $table->foreignId('income_id')->nullable()->constrained('incomes')->nullOnDelete();
            $table->decimal('amount', 15, 2);
            $table->date('date');
            $table->string('type')->default('received');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['household_id', 'date']);
            $table->index(['receivable_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receivable_payments');
    }
};
