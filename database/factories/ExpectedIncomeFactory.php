<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ExpectedIncome;
use App\Models\Household;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExpectedIncome>
 *
 * Datos FALSOS: nunca ingresos reales de personas concretas.
 */
class ExpectedIncomeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'household_id' => Household::factory(),
            'category_id' => null,
            'name' => fake()->randomElement(['Salario', 'Arriendo local', 'Freelance', 'Inversiones', 'Pensión']),
            'amount' => fake()->numberBetween(8000, 60000) * 100, // montos COP realistas
            'day_of_month' => fake()->numberBetween(1, 28),
            'is_active' => true,
            'notes' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
