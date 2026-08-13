<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\HouseholdRole;
use App\Enums\InvitationStatus;
use App\Models\Household;
use App\Models\HouseholdInvitation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<HouseholdInvitation>
 */
class HouseholdInvitationFactory extends Factory
{
    /**
     * Define the model's default state.
     * El token se guarda ya hasheado, igual que en el flujo real.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'household_id' => Household::factory(),
            'email' => fake()->safeEmail(),
            'token' => hash('sha256', Str::random(64)),
            'role' => HouseholdRole::Member,
            'status' => InvitationStatus::Pending,
            'expires_at' => now()->addDays(7),
            'accepted_at' => null,
            'accepted_by_user_id' => null,
        ];
    }
}
