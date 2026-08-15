<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AccountType;
use App\Models\Account;
use App\Models\Household;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Account>
 */
class AccountFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'household_id' => Household::factory(),
            'name' => fake()->randomElement(['Efectivo', 'Davivienda ahorros', 'Bancolombia', 'Nequi', 'Daviplata', 'Cuenta corriente']),
            'type' => fake()->randomElement(AccountType::cases())->value,
            'initial_balance' => fake()->randomElement([0, 50000, 100000, 500000, 1200000]),
            'current_balance' => 0, // lo recalcula el servicio; 0 como valor neutro inicial
            'currency' => 'COP',
            'is_active' => true,
            'notes' => null,
        ];
    }

    /**
     * Marca el saldo actual igual al inicial (cuenta sin movimientos).
     */
    public function withInitialBalance(float|string $balance): static
    {
        return $this->state(fn (array $attributes) => [
            'initial_balance' => $balance,
            'current_balance' => $balance,
        ]);
    }
}
