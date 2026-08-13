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
        Schema::create('households', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // Creador/admin del hogar.
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('currency', 8)->default('COP');
            $table->string('timezone', 64)->default('America/Bogota');
            $table->timestamps();
            $table->softDeletes();

            $table->index('owner_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('households');
    }
};
