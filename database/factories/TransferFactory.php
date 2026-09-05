<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Account;
use App\Models\Household;
use App\Models\Transfer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transfer>
 *
 * Para datos aislados coherentes, pasa explícitamente household_id,
 * user_id, from_account_id y to_account_id (ambas cuentas deben pertenecer
 * al mismo hogar y ser distintas).
 */
class TransferFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'household_id' => Household::factory(),
            'user_id' => User::factory(),
            'from_account_id' => Account::factory(),
            'to_account_id' => Account::factory(),
            'amount' => fake()->numberBetween(10000, 5000000),
            'date' => fake()->dateTimeBetween('-3 months', 'now')->format('Y-m-d'),
            'description' => fake()->optional()->sentence(3),
            'notes' => null,
        ];
    }
}
