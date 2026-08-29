<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Atributos propios de una tarjeta de crédito (Épica 6), complementarios
     * a su `account` con type=credit_card (ADR-0002). No duplican el saldo:
     * ese vive en la cuenta y en la deuda.
     *
     * ⚠️ SEGURIDAD (docs/SECURITY.md §4): aquí NO se guarda —ni se guardará—
     * número completo de tarjeta, CVV ni PIN. No existen esas columnas a
     * propósito: lo que no se almacena no se puede filtrar.
     */
    public function up(): void
    {
        Schema::create('credit_cards', function (Blueprint $table) {
            $table->id();
            // Una tarjeta por cuenta.
            $table->foreignId('account_id')->unique()->constrained('accounts')->cascadeOnDelete();
            $table->foreignId('household_id')->constrained('households')->cascadeOnDelete();
            $table->decimal('credit_limit', 15, 2);
            // Cupo disponible: se recalcula desde el cupo menos lo usado.
            $table->decimal('available_credit', 15, 2);
            $table->unsignedTinyInteger('statement_date')->nullable();    // día de corte (1-31)
            $table->unsignedTinyInteger('payment_due_date')->nullable();  // día límite de pago (1-31)
            $table->decimal('annual_fee', 15, 2)->nullable();             // cuota de manejo anual
            $table->decimal('monthly_fee', 15, 2)->nullable();            // cuota de manejo mensual
            $table->timestamps();

            $table->index('household_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_cards');
    }
};
