<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DebtPaymentType;
use App\Models\Debt;
use App\Models\DebtPayment;
use App\Models\Household;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DebtPayment>
 */
class DebtPaymentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'debt_id' => Debt::factory(),
            'household_id' => Household::factory(),
            'expense_id' => null,
            'amount' => fake()->numberBetween(500, 20000) * 100,
            'date' => fake()->dateTimeBetween('-6 months', 'now')->format('Y-m-d'),
            'type' => DebtPaymentType::Scheduled->value,
            'notes' => null,
        ];
    }

    /**
     * Pago coherente con una deuda ya creada (mismo hogar).
     */
    public function forDebt(Debt $debt): static
    {
        return $this->state(fn () => [
            'debt_id' => $debt->id,
            'household_id' => $debt->household_id,
        ]);
    }
}
