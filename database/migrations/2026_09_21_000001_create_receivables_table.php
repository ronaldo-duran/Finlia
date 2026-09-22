<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cuentas por cobrar del hogar (Épica 15): dinero que le deben al hogar.
     * Espejo estructural de `debts` — mismo patrón de saldo derivado
     * (ADR-0020 espejo): `current_balance` se RECALCULA a partir de los
     * cobros registrados, nunca se teclea.
     */
    public function up(): void
    {
        Schema::create('receivables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained('households')->cascadeOnDelete();
            $table->string('debtor_name');
            $table->foreignId('debtor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('original_amount', 15, 2);
            $table->decimal('current_balance', 15, 2);
            $table->string('currency', 3)->default('COP');
            $table->string('status')->default('pending');
            $table->date('due_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['household_id', 'status']);
            $table->index(['household_id', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receivables');
    }
};
