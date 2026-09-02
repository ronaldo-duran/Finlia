<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Datos demográficos mínimos del usuario (Plan 04, ADR-0032):
 * fecha de nacimiento, región y género.
 *
 * Los tres son NULL en DB: los usuarios heredados no los tienen (el
 * registro los exige a partir de ahora, el perfil los completa), y la
 * región y el género son opcionales SIEMPRE (minimización, Ley 1581).
 * Nada derivado: la edad se calcula con User::age(), nunca se almacena.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->date('birth_date')->nullable()->after('password');
            // Valor = slug del enum ColombianRegion (p. ej. 'cundinamarca').
            $table->string('region', 40)->nullable()->after('birth_date');
            // Valor del enum Gender o NULL = "prefiero no decirlo".
            $table->string('gender', 20)->nullable()->after('region');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['birth_date', 'region', 'gender']);
        });
    }
};
