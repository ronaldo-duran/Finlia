<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Household;
use App\Models\McpToken;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<McpToken>
 */
class McpTokenFactory extends Factory
{
    protected $model = McpToken::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'household_id' => Household::factory(),
            'name' => fake()->randomElement(['Claude Desktop', 'Automatización', 'Pruebas']),
            'token_hash' => hash('sha256', Str::random(40)),
            'abilities' => [McpToken::ABILITY_READ],
            'last_used_at' => null,
            'expires_at' => null,
            'revoked_at' => null,
        ];
    }

    public function revoked(): static
    {
        return $this->state(fn () => ['revoked_at' => now()]);
    }

    public function expired(): static
    {
        return $this->state(fn () => ['expires_at' => now()->subDay()]);
    }
}
