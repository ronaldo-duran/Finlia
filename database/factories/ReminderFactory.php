<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Frequency;
use App\Enums\ReminderStatus;
use App\Models\Household;
use App\Models\Reminder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reminder>
 *
 * Datos FALSOS: nunca recordatorios reales de personas concretas.
 */
class ReminderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'household_id' => Household::factory(),
            'title' => fake()->randomElement([
                'Tecnomecánica', 'Renovar SOAT', 'Renovar pasaporte',
                'Revisión del carro', 'Mantenimiento del computador',
            ]),
            'amount' => fake()->randomElement([null, fake()->numberBetween(5, 900) * 1000]),
            'due_date' => fake()->dateTimeBetween('-10 days', '+2 months')->format('Y-m-d'),
            'frequency' => null, // suelto por defecto; usar recurring() para el anual
            'status' => ReminderStatus::Pending->value,
            'notes' => null,
        ];
    }

    /** Obligación anual (SOAT, tecnomecánica): se repite cada año. */
    public function yearly(): static
    {
        return $this->state(fn () => ['frequency' => Frequency::Yearly->value]);
    }

    public function completed(): static
    {
        return $this->state(fn () => ['status' => ReminderStatus::Completed->value]);
    }
}
