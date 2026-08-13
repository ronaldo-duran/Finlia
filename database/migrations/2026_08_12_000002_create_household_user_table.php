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
        // Pivot: membresía de usuarios en hogares (multi-hogar por usuario).
        Schema::create('household_user', function (Blueprint $table) {
            $table->foreignId('household_id')->constrained('households')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role')->default('member'); // owner | member → App\Enums\HouseholdRole
            $table->timestamp('joined_at')->useCurrent();
            $table->timestamps();

            // Clave primaria compuesta: un usuario pertenece una sola vez a cada hogar.
            $table->primary(['household_id', 'user_id']);
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('household_user');
    }
};
