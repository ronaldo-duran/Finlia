<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Household;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Household>
 */
class HouseholdFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Mi hogar', 'Familia', 'Casa', 'Hogar de '.fake()->lastName()]),
            'owner_id' => User::factory(),
            'currency' => 'COP',
            'timezone' => 'America/Bogota',
        ];
    }
}
