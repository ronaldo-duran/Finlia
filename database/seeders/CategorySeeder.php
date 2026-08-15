<?php

namespace Database\Seeders;

use App\Enums\CategoryType;
use App\Models\Category;
use Illuminate\Database\Seeder;

/**
 * Categorías globales (household_id NULL) disponibles para todos los hogares.
 * Datos FALSOS de catálogo (no son datos financieros reales).
 */
class CategorySeeder extends Seeder
{
    /**
     * Definición: nombre => color.
     */
    private array $expenses = [
        'Alimentación' => '#ef4444',
        'Vivienda' => '#f59e0b',
        'Transporte' => '#3b82f6',
        'Salud' => '#ec4899',
        'Mascotas' => '#a855f7',
        'Entretenimiento' => '#14b8a6',
        'Educación' => '#6366f1',
        'Deudas' => '#64748b',
        'Servicios' => '#0ea5e9',
        'Compras' => '#f97316',
        'Otros' => '#94a3b8',
    ];

    private array $incomes = [
        'Salario' => '#16a34a',
        'Freelance' => '#22c55e',
        'Inversión' => '#10b981',
        'Regalo' => '#84cc16',
        'Otros ingresos' => '#65a30d',
    ];

    public function run(): void
    {
        foreach ($this->expenses as $name => $color) {
            Category::firstOrCreate(
                ['household_id' => null, 'name' => $name, 'type' => CategoryType::Expense->value],
                ['color' => $color, 'icon' => null, 'is_default' => true],
            );
        }

        foreach ($this->incomes as $name => $color) {
            Category::firstOrCreate(
                ['household_id' => null, 'name' => $name, 'type' => CategoryType::Income->value],
                ['color' => $color, 'icon' => null, 'is_default' => true],
            );
        }
    }
}
