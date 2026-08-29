<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DebtStatus;
use App\Enums\DebtType;
use App\Enums\InterestRateType;
use App\Models\Debt;
use App\Models\Household;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Debt>
 *
 * Datos FALSOS: nunca deudas reales de personas concretas.
 */
class DebtFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $original = fake()->numberBetween(8000, 300000) * 100; // montos COP realistas

        return [
            'household_id' => Household::factory(),
            'account_id' => null,
            'name' => fake()->randomElement(['Tarjeta banco', 'Préstamo de libre inversión', 'Crédito moto', 'Préstamo familiar']),
            'institution' => fake()->randomElement(['Banco Nacional', 'Cooperativa', 'Financiera', null]),
            'type' => fake()->randomElement(DebtType::cases())->value,
            'original_amount' => $original,
            'current_balance' => $original,
            'interest_rate' => fake()->randomFloat(3, 8, 32),
            'interest_rate_type' => InterestRateType::Fixed->value,
            'minimum_payment' => round($original * 0.02, 2),
            'scheduled_payment' => round($original * 0.05, 2),
            'due_day' => fake()->numberBetween(1, 28),
            'start_date' => fake()->dateTimeBetween('-2 years', '-1 month')->format('Y-m-d'),
            'end_date' => null,
            'status' => DebtStatus::Active->value,
            'notes' => null,
        ];
    }

    public function creditCard(): static
    {
        return $this->state(fn () => ['type' => DebtType::CreditCard->value]);
    }

    public function paid(): static
    {
        return $this->state(fn () => ['current_balance' => 0, 'status' => DebtStatus::Paid->value]);
    }

    /** Sin cuota registrada: no se puede proyectar el fin de la deuda. */
    public function withoutInstallment(): static
    {
        return $this->state(fn () => ['minimum_payment' => null, 'scheduled_payment' => null]);
    }
}
