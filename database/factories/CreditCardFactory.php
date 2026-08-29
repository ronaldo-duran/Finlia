<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Account;
use App\Models\CreditCard;
use App\Models\Household;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CreditCard>
 *
 * Nunca genera número de tarjeta, CVV ni PIN: esas columnas no existen
 * (docs/SECURITY.md §4).
 */
class CreditCardFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $limit = fake()->numberBetween(10000, 200000) * 100;

        return [
            'account_id' => Account::factory(),
            'household_id' => Household::factory(),
            'credit_limit' => $limit,
            'available_credit' => $limit,
            'statement_date' => fake()->numberBetween(1, 28),
            'payment_due_date' => fake()->numberBetween(1, 28),
            'annual_fee' => fake()->numberBetween(0, 800) * 100,
            'monthly_fee' => null,
        ];
    }
}
