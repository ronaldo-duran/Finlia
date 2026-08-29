<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SavingsGoalContributionType;
use App\Models\Household;
use App\Models\SavingsGoal;
use App\Models\SavingsGoalContribution;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SavingsGoalContribution>
 *
 * Datos FALSOS: nunca movimientos reales de personas concretas.
 */
class SavingsGoalContributionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'savings_goal_id' => SavingsGoal::factory(),
            'household_id' => Household::factory(),
            'amount' => fake()->numberBetween(200, 2000) * 100,
            'type' => SavingsGoalContributionType::Deposit->value,
            'date' => fake()->dateTimeBetween('-6 months', 'now')->format('Y-m-d'),
            'notes' => null,
        ];
    }

    public function withdrawal(): static
    {
        return $this->state(fn () => ['type' => SavingsGoalContributionType::Withdrawal->value]);
    }
}
