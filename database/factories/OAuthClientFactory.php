<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\OAuthClient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OAuthClient>
 */
class OAuthClientFactory extends Factory
{
    protected $model = OAuthClient::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'client_id' => 'finlia_client_'.bin2hex(random_bytes(8)),
            'client_name' => fake()->randomElement(['Claude.ai', 'ChatGPT Connector', 'Test Client']),
            'redirect_uris' => ['https://claude.ai/api/mcp/auth_callback'],
            'grant_types' => ['authorization_code', 'refresh_token'],
            'response_types' => ['code'],
            'token_endpoint_auth_method' => 'none',
            'scope' => 'mcp:read',
            'client_uri' => null,
            'logo_uri' => null,
            'software_id' => null,
            'software_version' => null,
        ];
    }
}
