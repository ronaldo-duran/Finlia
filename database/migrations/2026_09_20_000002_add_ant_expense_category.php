<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Categoría global "Gasto hormiga" (Épica 12).
 *
 * Nombre del hábito de gastar poco pero seguido — cafés, snacks, pequeñas
 * suscripciones — que al mes suma más de lo que se cree. Vive como categoría
 * global (`household_id` NULL) para que todos los hogares la vean sin
 * duplicarla, igual que "Alimentación" o "Transporte" (ADR-0005).
 *
 * Idempotente: si la fila ya existe se deja intacta. Sin dependencia de
 * `Category::firstOrCreate()` a propósito — los seeders viven fuera de las
 * migraciones y una migración no debe importar clases de dominio.
 */
return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::table('categories')
            ->whereNull('household_id')
            ->where('name', 'Gasto hormiga')
            ->where('type', 'expense')
            ->exists();

        if ($exists) {
            return;
        }

        DB::table('categories')->insert([
            'household_id' => null,
            'name' => 'Gasto hormiga',
            'type' => 'expense',
            'color' => '#d97706',
            'icon' => null,
            'is_default' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('categories')
            ->whereNull('household_id')
            ->where('name', 'Gasto hormiga')
            ->where('type', 'expense')
            ->delete();
    }
};
