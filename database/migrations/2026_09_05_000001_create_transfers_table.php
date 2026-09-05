<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla de transferencias entre cuentas del hogar (Épica 10, ADR-0035).
 *
 * Una transferencia mueve dinero de una cuenta a otra dentro del mismo
 * hogar. No es ingreso ni gasto para el P&L del hogar, pero sí afecta
 * el saldo de ambas cuentas (AccountBalanceService la suma/resta).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transfers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('household_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->comment('Usuario que registró la transferencia')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('from_account_id')
                ->comment('Cuenta de origen (pierde saldo)')
                ->constrained('accounts')
                ->cascadeOnDelete();

            $table->foreignId('to_account_id')
                ->comment('Cuenta de destino (gana saldo)')
                ->constrained('accounts')
                ->cascadeOnDelete();

            $table->decimal('amount', 15, 2);

            $table->date('date');

            $table->string('description', 200)->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();

            // Consultas frecuentes: lista de movimientos agrupada por fecha.
            $table->index(['household_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfers');
    }
};
