<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Debt;
use App\Models\DebtRefinancing;
use App\Models\Household;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DebtRefinancing>
 */
class DebtRefinancingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'debt_id' => Debt::factory(),
            'household_id' => Household::factory(),
            'refinanced_balance' => fake()->numberBetween(5000, 200000) * 100,
            'interest_rate' => fake()->randomFloat(3, 8, 28),
            'term_months' => fake()->randomElement([12, 24, 36, 48]),
            'installment' => fake()->numberBetween(2000, 30000) * 100,
            'start_date' => fake()->dateTimeBetween('-6 months', 'now')->format('Y-m-d'),
            'notes' => null,
        ];
    }

    public function forDebt(Debt $debt): static
    {
        return $this->state(fn () => [
            'debt_id' => $debt->id,
            'household_id' => $debt->household_id,
        ]);
    }
}
