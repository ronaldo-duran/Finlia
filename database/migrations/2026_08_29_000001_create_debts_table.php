<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Deudas del hogar (Épica 6): tarjetas, préstamos, crédito de vehículo,
     * préstamo familiar. Alimenta el término `debt` del dinero disponible
     * (ADR-0014) y el panel de deuda.
     *
     * `current_balance` se RECALCULA desde la línea base (ADR-0020): no lo
     * teclea el usuario, sale de original_amount (o del saldo refinanciado
     * más reciente) menos los pagos registrados.
     */
    public function up(): void
    {
        Schema::create('debts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained('households')->cascadeOnDelete();
            $table->foreignId('account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->string('name');
            $table->string('institution')->nullable();
            $table->string('type');
            $table->decimal('original_amount', 15, 2);
            $table->decimal('current_balance', 15, 2);
            $table->decimal('interest_rate', 6, 3)->nullable();
            $table->string('interest_rate_type')->nullable();
            $table->decimal('minimum_payment', 15, 2)->nullable();
            $table->decimal('scheduled_payment', 15, 2)->nullable();
            $table->unsignedTinyInteger('due_day')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('status')->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['household_id', 'status']);
            $table->index(['household_id', 'due_day']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('debts');
    }
};
