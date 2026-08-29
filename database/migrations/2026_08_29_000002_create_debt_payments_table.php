<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pagos registrados contra una deuda (Épica 6). Son la fuente de verdad
     * del saldo: `debts.current_balance` se recalcula a partir de ellos
     * (ADR-0020).
     *
     * Si el pago se hizo desde una cuenta del hogar, `expense_id` enlaza el
     * gasto real que movió el saldo de esa cuenta (ADR-0021), para que el
     * dinero no aparezca ni de más ni de menos.
     */
    public function up(): void
    {
        Schema::create('debt_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('debt_id')->constrained('debts')->cascadeOnDelete();
            // Denormalizado a propósito: permite acotar por hogar sin join
            // (aislamiento multi-hogar, amenaza #1).
            $table->foreignId('household_id')->constrained('households')->cascadeOnDelete();
            // Gasto generado, si el pago salió de una cuenta del hogar.
            // nullOnDelete: borrar el movimiento no borra el historial de deuda.
            $table->foreignId('expense_id')->nullable()->constrained('expenses')->nullOnDelete();
            $table->decimal('amount', 15, 2);
            $table->date('date');
            $table->string('type')->default('scheduled'); // enum App\Enums\DebtPaymentType
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['household_id', 'date']);
            $table->index(['debt_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('debt_payments');
    }
};
