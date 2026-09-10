<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ContactReason;
use App\Models\ContactMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContactMessage>
 */
class ContactMessageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reason' => fake()->randomElement(ContactReason::public())->value,
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'body' => fake()->paragraph(),
            'user_id' => null,
            'ip_address' => fake()->ipv4(),
            'context' => null,
        ];
    }

    /**
     * Reporte de error: con autor y contexto técnico, sin IP.
     */
    public function bugReport(?User $user = null): static
    {
        return $this->state(fn () => [
            'reason' => ContactReason::Bug->value,
            'user_id' => $user?->id ?? User::factory(),
            'ip_address' => null,
            'context' => [
                'Versión' => '0.30.0',
                'Pantalla' => 'https://app.finlia.test/movimientos',
                'Viewport' => '390x844',
            ],
        ]);
    }
}
