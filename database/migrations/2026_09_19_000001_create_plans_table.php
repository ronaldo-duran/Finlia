<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo de planes de Finlia (Épica 12).
 *
 * `features` y `limits` van como JSON para que añadir una feature no
 * cueste una migración: es una fila en el seeder y un caso en el enum
 * (`App\Enums\PlanFeature`/`PlanLimit`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 40)->unique();
            $table->string('name', 60);
            $table->decimal('price_monthly', 15, 2)->nullable();
            $table->decimal('price_yearly', 15, 2)->nullable();
            $table->json('features');
            $table->json('limits');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
