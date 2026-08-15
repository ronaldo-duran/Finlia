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
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained('households')->cascadeOnDelete();
            $table->string('name');
            // Tipo de cuenta (App\Enums\AccountType): cash, bank, savings, ...
            $table->string('type')->default('cash');
            // Saldo inicial (inmutable tras crear). DECIMAL(15,2) (ADR-0006).
            $table->decimal('initial_balance', 15, 2)->default(0);
            // Saldo actual: persistido y recomputado desde movimientos (ADR-0012).
            $table->decimal('current_balance', 15, 2)->default(0);
            $table->string('currency', 8)->default('COP');
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('household_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
