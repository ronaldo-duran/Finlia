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
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            // household_id NULL = categoría global (seed). Propia del hogar en caso contrario.
            $table->foreignId('household_id')->nullable()->constrained('households')->nullOnDelete();
            $table->string('name');
            // Tipo (App\Enums\CategoryType): income | expense.
            $table->string('type');
            $table->string('color', 7)->nullable(); // #RRGGBB para gráficos
            $table->string('icon')->nullable();
            $table->boolean('is_default')->default(false); // true para las del seed global
            $table->timestamps();

            $table->index(['household_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
