<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SavingsGoalPriority;
use App\Enums\SavingsGoalStatus;
use App\Models\Household;
use App\Models\SavingsGoal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SavingsGoal>
 *
 * Datos FALSOS: nunca metas reales de personas concretas.
 */
class SavingsGoalFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $target = fake()->numberBetween(5000, 400000) * 100; // montos COP realistas

        return [
            'household_id' => Household::factory(),
            'name' => fake()->randomElement([
                'Fondo de emergencia', 'Viaje a San Andrés', 'Cuota inicial vivienda',
                'Computador nuevo', 'SOAT y papeles del carro', 'Vacaciones de diciembre',
            ]),
            'target_amount' => $target,
            'current_amount' => 0, // derivado: lo fija SavingsGoalService al aportar (ADR-0025)
            'target_date' => fake()->dateTimeBetween('+1 month', '+2 years')->format('Y-m-d'),
            'priority' => fake()->randomElement(SavingsGoalPriority::cases())->value,
            'status' => SavingsGoalStatus::Active->value,
            'monthly_commitment' => round($target / 12, 2),
            'is_emergency_fund' => false,
            'notes' => null,
        ];
    }

    /** Fondo de emergencia: sin fecha límite y prioridad alta. */
    public function emergencyFund(): static
    {
        return $this->state(fn () => [
            'name' => 'Fondo de emergencia',
            'is_emergency_fund' => true,
            'priority' => SavingsGoalPriority::High->value,
            'target_date' => null,
        ]);
    }

    public function paused(): static
    {
        return $this->state(fn () => ['status' => SavingsGoalStatus::Paused->value]);
    }

    public function completed(): static
    {
        return $this->state(fn () => ['status' => SavingsGoalStatus::Completed->value]);
    }

    public function archived(): static
    {
        return $this->state(fn () => ['status' => SavingsGoalStatus::Archived->value]);
    }
}
