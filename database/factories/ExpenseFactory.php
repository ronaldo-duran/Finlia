<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Models\Account;
use App\Models\Expense;
use App\Models\Household;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 *
 * Nota: para datos aislados coherentes, pasa explícitamente household_id,
 * user_id y account_id (account debe pertenecer al mismo hogar).
 */
class ExpenseFactory extends Factory
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
            'amount' => fake()->numberBetween(5000, 800000) / 100 * 100, // COP redondeado
            'date' => fake()->dateTimeBetween('-1 month', 'now')->format('Y-m-d'),
            'description' => fake()->optional()->sentence(3),
            'notes' => null,
            'payment_method' => fake()->randomElement(PaymentMethod::cases())->value,
        ];
    }
}
