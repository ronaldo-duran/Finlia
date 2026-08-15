<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Account;
use App\Models\Household;
use App\Models\Income;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Income>
 *
 * Nota: para datos aislados coherentes, pasa explícitamente household_id,
 * user_id y account_id (account debe pertenecer al mismo hogar).
 */
class IncomeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'household_id' => Household::factory(),
            'user_id' => User::factory(),
            'account_id' => Account::factory(),
            'category_id' => null,
            'amount' => fake()->numberBetween(500000, 5000000) / 100 * 100, // COP redondeado
            'date' => fake()->dateTimeBetween('-1 month', 'now')->format('Y-m-d'),
            'description' => fake()->optional()->sentence(3),
            'notes' => null,
            'source' => fake()->randomElement(['Salario', 'Freelance', 'Inversión', 'Regalo', null]),
        ];
    }
}
