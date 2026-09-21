<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ReceivablePaymentType;
use App\Models\Household;
use App\Models\Receivable;
use App\Models\ReceivablePayment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReceivablePayment>
 */
class ReceivablePaymentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'receivable_id' => Receivable::factory(),
            'household_id' => Household::factory(),
            'account_id' => null,
            'income_id' => null,
            'amount' => fake()->numberBetween(500, 20000) * 100,
            'date' => fake()->dateTimeBetween('-3 months', 'now')->format('Y-m-d'),
            'type' => ReceivablePaymentType::Received->value,
            'notes' => null,
        ];
    }

    /**
     * Cobro coherente con una cuenta por cobrar ya creada (mismo hogar).
     */
    public function forReceivable(Receivable $receivable): static
    {
        return $this->state(fn () => [
            'receivable_id' => $receivable->id,
            'household_id' => $receivable->household_id,
        ]);
    }
}
