<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Household;
use App\Models\OAuthAccessToken;
use App\Models\OAuthClient;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<OAuthAccessToken>
 */
class OAuthAccessTokenFactory extends Factory
{
    protected $model = OAuthAccessToken::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'oauth_client_id' => OAuthClient::factory(),
            'user_id' => User::factory(),
            'household_id' => Household::factory(),
            'token_hash' => hash('sha256', Str::random(40)),
            'scope' => 'mcp:read',
            'last_used_at' => null,
            'expires_at' => now()->addHour(),
            'revoked_at' => null,
        ];
    }
}
