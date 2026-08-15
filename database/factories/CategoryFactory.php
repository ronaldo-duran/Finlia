<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CategoryType;
use App\Models\Category;
use App\Models\Household;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'household_id' => Household::factory(),
            'name' => fake()->randomElement(['Comida', 'Ropa', 'Salud', 'Transporte', 'Ocio', 'Facturas']),
            'type' => fake()->randomElement(CategoryType::cases())->value,
            'color' => fake()->hexColor(),
            'icon' => null,
            'is_default' => false,
        ];
    }
}
