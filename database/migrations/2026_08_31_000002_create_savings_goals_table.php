<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Metas de ahorro del hogar (Épica 7): fondo de emergencia, viaje,
     * cuota inicial… Alimenta el término `savings` del dinero disponible
     * (ADR-0014) a través del aporte mensual programado.
     *
     * `current_amount` se RECALCULA desde los aportes y retiros registrados
     * (ADR-0025, espejo del saldo de deuda en ADR-0020): no lo teclea el
     * usuario, y borrar un movimiento lo deshace.
     */
    public function up(): void
    {
        Schema::create('savings_goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained('households')->cascadeOnDelete();
            $table->string('name');
            $table->decimal('target_amount', 15, 2);
            $table->decimal('current_amount', 15, 2)->default(0);
            $table->date('target_date')->nullable();
            $table->string('priority')->nullable();
            $table->string('status')->default('active');
            $table->decimal('monthly_commitment', 15, 2)->nullable();
            $table->boolean('is_emergency_fund')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['household_id', 'status']);
            $table->index(['household_id', 'target_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('savings_goals');
    }
};
