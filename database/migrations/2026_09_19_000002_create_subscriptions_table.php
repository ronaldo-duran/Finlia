<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Suscripción de un hogar a un plan (Épica 12).
 *
 * Un hogar tiene UNA suscripción a la vez; se identifica por
 * `(household_id)` en el índice único parcial de PHP (aquí se garantiza
 * como índice normal y se blinda por lógica de servicio).
 *
 * `status` se guarda como string validado por `App\Enums\SubscriptionStatus`
 * en aplicación (no ENUM de motor, ver DATA_MODEL).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('household_id')
                ->constrained('households')
                ->cascadeOnDelete();
            $table->foreignId('plan_id')
                ->constrained('plans')
                ->restrictOnDelete();
            $table->string('status', 20);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('renews_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->string('reason', 200)->nullable();
            $table->timestamps();

            // Un solo registro activo por hogar: consultas siempre acotadas
            // por (household_id, status).
            $table->index(['household_id', 'status']);
            $table->unique('household_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
