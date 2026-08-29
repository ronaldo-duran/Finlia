<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Movimientos sobre una meta de ahorro (Épica 7): aportes (deposit) y
     * retiros (withdrawal). Son la fuente de verdad de `current_amount`
     * (ADR-0025).
     *
     * NO mueven cuentas ni crean gastos (ADR-0025): ahorrar no es gastar, y
     * la transferencia entre cuentas quedó diferida a la Épica 10. Son
     * registro de progreso de la meta.
     */
    public function up(): void
    {
        Schema::create('savings_goal_contributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('savings_goal_id')->constrained('savings_goals')->cascadeOnDelete();
            // Denormalizado a propósito: permite acotar por hogar sin join
            // (aislamiento multi-hogar, amenaza #1).
            $table->foreignId('household_id')->constrained('households')->cascadeOnDelete();
            $table->decimal('amount', 15, 2);             // siempre positivo; la dirección la da `type`
            $table->string('type');                       // enum App\Enums\SavingsGoalContributionType
            $table->date('date');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['household_id', 'date']);
            $table->index(['savings_goal_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('savings_goal_contributions');
    }
};
