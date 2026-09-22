<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ReceivableStatus;
use App\Models\Household;
use App\Models\Receivable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Receivable>
 *
 * Datos FALSOS: nunca nombres ni importes reales de nadie.
 */
class ReceivableFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $original = fake()->numberBetween(500, 50000) * 100;

        return [
            'household_id' => Household::factory(),
            'debtor_name' => fake()->name(),
            'debtor_user_id' => null,
            'name' => fake()->randomElement([
                'Préstamo personal', 'Trabajo facturado', 'Adelanto de arriendo', 'Vaca colectiva',
            ]),
            'description' => null,
            'original_amount' => $original,
            'current_balance' => $original,
            'currency' => 'COP',
            'status' => ReceivableStatus::Pending->value,
            'due_date' => fake()->dateTimeBetween('now', '+3 months')->format('Y-m-d'),
            'notes' => null,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn () => [
            'current_balance' => 0,
            'status' => ReceivableStatus::Paid->value,
        ]);
    }

    public function partial(): static
    {
        return $this->state(function (array $attributes) {
            $original = (float) $attributes['original_amount'];

            return [
                'current_balance' => round($original / 2, 2),
                'status' => ReceivableStatus::Partial->value,
            ];
        });
    }

    public function writtenOff(): static
    {
        return $this->state(fn () => [
            'status' => ReceivableStatus::WrittenOff->value,
        ]);
    }

    public function withoutDueDate(): static
    {
        return $this->state(fn () => ['due_date' => null]);
    }
}
