<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Frequency;
use App\Models\Household;
use App\Models\RecurringExpense;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecurringExpense>
 *
 * Datos FALSOS: nunca obligaciones reales de personas concretas.
 */
class RecurringExpenseFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'household_id' => Household::factory(),
            'category_id' => null,
            'account_id' => null,
            'name' => fake()->randomElement(['Arriendo', 'Internet', 'SOAT', 'Suscripción', 'Mantenimiento moto', 'Seguro']),
            'amount' => fake()->numberBetween(1500, 90000) * 100, // montos COP realistas
            'frequency' => fake()->randomElement(Frequency::cases())->value,
            'frequency_interval' => null,
            'next_date' => fake()->dateTimeBetween('now', '+45 days')->format('Y-m-d'),
            'is_active' => true,
            'notes' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function yearly(): static
    {
        return $this->state(fn () => ['frequency' => Frequency::Yearly->value]);
    }

    public function monthly(): static
    {
        return $this->state(fn () => ['frequency' => Frequency::Monthly->value]);
    }

    /**
     * Obligación vencida: próxima fecha en el pasado.
     */
    public function overdue(): static
    {
        return $this->state(fn () => ['next_date' => fake()->dateTimeBetween('-30 days', '-1 day')->format('Y-m-d')]);
    }
}
