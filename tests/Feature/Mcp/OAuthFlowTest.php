<?php

declare(strict_types=1);

namespace Tests\Feature\Mcp;

use App\Models\Household;
use App\Models\OAuthAccessToken;
use App\Models\OAuthAuthorizationCode;
use App\Models\OAuthClient;
use App\Models\OAuthRefreshToken;
use App\Models\User;
use App\Services\HouseholdService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Flujo OAuth 2.1 + PKCE completo para conectores MCP externos.
 */
class OAuthFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Household $household;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->household = app(HouseholdService::class)->createHousehold($this->user->id, 'Hogar OAuth');
    }

    public function test_metadata_protected_resource_apunta_al_authorization_server(): void
    {
        $response = $this->getJson('/.well-known/oauth-protected-resource');

        $response->assertOk();
        $response->assertJsonPath('resource', url('/mcp'));
        $response->assertJsonPath('authorization_servers.0', url('/'));
        $response->assertJsonPath('scopes_supported.0', 'mcp:read');
    }

    public function test_metadata_authorization_server_lista_endpoints_y_pkce(): void
    {
        $response = $this->getJson('/.well-known/oauth-authorization-server');

        $response->assertOk();
        $response->assertJsonPath('issuer', url('/'));
        $response->assertJsonPath('authorization_endpoint', url('/oauth/authorize'));
        $response->assertJsonPath('token_endpoint', url('/oauth/token'));
        $response->assertJsonPath('registration_endpoint', url('/oauth/register'));
        $response->assertJsonPath('code_challenge_methods_supported.0', 'S256');
        $response->assertJsonPath('grant_types_supported.0', 'authorization_code');
    }

    public function test_dynamic_client_registration_emite_client_id(): void
    {
        $response = $this->postJson('/oauth/register', [
            'client_name' => 'Test MCP Client',
            'redirect_uris' => ['https://claude.ai/api/mcp/auth_callback'],
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure(['client_id', 'client_id_issued_at', 'client_name', 'redirect_uris']);
        $this->assertStringStartsWith('finlia_client_', $response->json('client_id'));
        $this->assertSame(1, OAuthClient::count());
    }

    public function test_registration_rechaza_metadata_invalido(): void
    {
        $response = $this->postJson('/oauth/register', [
            'client_name' => '',
            'redirect_uris' => [],
        ]);

        $response->assertStatus(400);
        $response->assertJsonPath('error', 'invalid_client_metadata');
    }

    public function test_authorize_requiere_login(): void
    {
        $client = OAuthClient::factory()->create();

        $response = $this->get('/oauth/authorize?'.http_build_query([
            'response_type' => 'code',
            'client_id' => $client->client_id,
            'redirect_uri' => $client->redirect_uris[0],
            'code_challenge' => $this->generateChallenge(),
            'code_challenge_method' => 'S256',
            'state' => 'xyz',
        ]));

        $response->assertRedirect('/login');
    }

    public function test_authorize_muestra_pantalla_de_consentimiento(): void
    {
        $this->actingAs($this->user);
        $client = OAuthClient::factory()->create(['client_name' => 'Claude.ai']);

        $response = $this->get('/oauth/authorize?'.http_build_query([
            'response_type' => 'code',
            'client_id' => $client->client_id,
            'redirect_uri' => $client->redirect_uris[0],
            'code_challenge' => $this->generateChallenge(),
            'code_challenge_method' => 'S256',
            'state' => 'xyz',
        ]));

        $response->assertOk();
        $response->assertSee('Claude.ai');
        $response->assertSee('Hogar OAuth');
        $response->assertSee('Autorizar');
    }

    public function test_approve_emite_codigo_y_redirige_al_cliente(): void
    {
        $this->actingAs($this->user);
        $client = OAuthClient::factory()->create();

        $response = $this->post('/oauth/authorize', [
            'response_type' => 'code',
            'client_id' => $client->client_id,
            'redirect_uri' => $client->redirect_uris[0],
            'code_challenge' => $this->generateChallenge(),
            'code_challenge_method' => 'S256',
            'state' => 'xyz',
            'scope' => 'mcp:read',
            'household_id' => $this->household->id,
            'decision' => 'approve',
        ]);

        $response->assertRedirect();
        $location = $response->headers->get('Location');
        $this->assertStringStartsWith($client->redirect_uris[0], $location);
        $this->assertStringContainsString('code=', $location);
        $this->assertStringContainsString('state=xyz', $location);
        $this->assertSame(1, OAuthAuthorizationCode::count());
    }

    public function test_approve_rechaza_hogar_ajeno(): void
    {
        $otro = User::factory()->create();
        $hogarAjeno = app(HouseholdService::class)->createHousehold($otro->id, 'Ajeno');

        $this->actingAs($this->user);
        $client = OAuthClient::factory()->create();

        $response = $this->post('/oauth/authorize', [
            'response_type' => 'code',
            'client_id' => $client->client_id,
            'redirect_uri' => $client->redirect_uris[0],
            'code_challenge' => $this->generateChallenge(),
            'code_challenge_method' => 'S256',
            'state' => 'xyz',
            'household_id' => $hogarAjeno->id,
            'decision' => 'approve',
        ]);

        $response->assertForbidden();
        $this->assertSame(0, OAuthAuthorizationCode::count());
    }

    public function test_approve_con_deny_devuelve_access_denied(): void
    {
        $this->actingAs($this->user);
        $client = OAuthClient::factory()->create();

        $response = $this->post('/oauth/authorize', [
            'response_type' => 'code',
            'client_id' => $client->client_id,
            'redirect_uri' => $client->redirect_uris[0],
            'code_challenge' => $this->generateChallenge(),
            'code_challenge_method' => 'S256',
            'state' => 'xyz',
            'decision' => 'deny',
        ]);

        $response->assertRedirect();
        $this->assertStringContainsString('error=access_denied', $response->headers->get('Location'));
        $this->assertSame(0, OAuthAuthorizationCode::count());
    }

    public function test_token_exchange_canjea_codigo_por_access_y_refresh(): void
    {
        $verifier = $this->generateVerifier();
        $client = OAuthClient::factory()->create();
        $code = $this->completeAuthorize($client, $verifier);

        $response = $this->postJson('/oauth/token', [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'client_id' => $client->client_id,
            'redirect_uri' => $client->redirect_uris[0],
            'code_verifier' => $verifier,
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['access_token', 'token_type', 'expires_in', 'refresh_token', 'scope']);
        $this->assertStringStartsWith('finlia_oat_', $response->json('access_token'));
        $this->assertStringStartsWith('finlia_ort_', $response->json('refresh_token'));
        $this->assertSame('Bearer', $response->json('token_type'));
    }

    public function test_token_exchange_rechaza_code_verifier_invalido(): void
    {
        $verifier = $this->generateVerifier();
        $client = OAuthClient::factory()->create();
        $code = $this->completeAuthorize($client, $verifier);

        $response = $this->postJson('/oauth/token', [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'client_id' => $client->client_id,
            'redirect_uri' => $client->redirect_uris[0],
            'code_verifier' => 'otro-verifier-cualquiera-que-nunca-coincide',
        ]);

        $response->assertStatus(400);
        $response->assertJsonPath('error', 'invalid_grant');
    }

    public function test_token_exchange_solo_canjea_el_codigo_una_vez(): void
    {
        $verifier = $this->generateVerifier();
        $client = OAuthClient::factory()->create();
        $code = $this->completeAuthorize($client, $verifier);

        $this->postJson('/oauth/token', [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'client_id' => $client->client_id,
            'redirect_uri' => $client->redirect_uris[0],
            'code_verifier' => $verifier,
        ])->assertOk();

        $response = $this->postJson('/oauth/token', [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'client_id' => $client->client_id,
            'redirect_uri' => $client->redirect_uris[0],
            'code_verifier' => $verifier,
        ]);

        $response->assertStatus(400);
        $response->assertJsonPath('error', 'invalid_grant');
    }

    public function test_refresh_rota_tokens_y_revoca_el_access_anterior(): void
    {
        [$access, $refresh] = $this->fullFlow();

        $response = $this->postJson('/oauth/token', [
            'grant_type' => 'refresh_token',
            'refresh_token' => $refresh,
            'client_id' => OAuthClient::first()->client_id,
        ]);

        $response->assertOk();
        $this->assertNotSame($access, $response->json('access_token'));
        $this->assertNotSame($refresh, $response->json('refresh_token'));

        $hash = hash('sha256', $access);
        $this->assertNotNull(OAuthAccessToken::query()->where('token_hash', $hash)->first()->revoked_at);

        $oldRefreshHash = hash('sha256', $refresh);
        $this->assertNotNull(OAuthRefreshToken::query()->where('token_hash', $oldRefreshHash)->first()->revoked_at);
    }

    public function test_mcp_endpoint_acepta_access_token_oauth(): void
    {
        [$access] = $this->fullFlow();

        $response = $this->postJson('/mcp', [
            'jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize',
        ], ['Authorization' => 'Bearer '.$access]);

        $response->assertOk();
        $response->assertJsonPath('result.serverInfo.name', 'finlia-mcp');
    }

    public function test_mcp_endpoint_401_incluye_www_authenticate_con_resource_metadata(): void
    {
        $response = $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize']);

        $response->assertStatus(401);
        $header = $response->headers->get('WWW-Authenticate');
        $this->assertNotNull($header);
        $this->assertStringContainsString('resource_metadata=', $header);
        $this->assertStringContainsString('/.well-known/oauth-protected-resource', $header);
    }

    public function test_access_token_expelido_del_hogar_pierde_el_acceso(): void
    {
        [$access] = $this->fullFlow();

        $this->user->households()->detach($this->household->id);

        $response = $this->postJson('/mcp', [
            'jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize',
        ], ['Authorization' => 'Bearer '.$access]);

        $response->assertStatus(401);
    }

    public function test_revoke_marca_el_token_como_revocado(): void
    {
        [$access] = $this->fullFlow();

        $this->postJson('/oauth/revoke', ['token' => $access])->assertOk();

        $response = $this->postJson('/mcp', [
            'jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize',
        ], ['Authorization' => 'Bearer '.$access]);

        $response->assertStatus(401);
    }

    public function test_revoke_siempre_responde_200_aunque_el_token_no_exista(): void
    {
        $response = $this->postJson('/oauth/revoke', ['token' => 'finlia_oat_desconocido']);
        $response->assertOk();
    }

    public function test_token_exchange_requiere_grant_type_soportado(): void
    {
        $response = $this->postJson('/oauth/token', ['grant_type' => 'password']);

        $response->assertStatus(400);
        $response->assertJsonPath('error', 'unsupported_grant_type');
    }

    private function generateVerifier(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    private function generateChallenge(?string $verifier = null): string
    {
        $verifier = $verifier ?? $this->generateVerifier();

        return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    }

    private function completeAuthorize(OAuthClient $client, string $verifier): string
    {
        $challenge = $this->generateChallenge($verifier);

        $this->actingAs($this->user);
        $response = $this->post('/oauth/authorize', [
            'response_type' => 'code',
            'client_id' => $client->client_id,
            'redirect_uri' => $client->redirect_uris[0],
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
            'state' => 'xyz',
            'household_id' => $this->household->id,
            'decision' => 'approve',
        ]);

        $location = $response->headers->get('Location');
        parse_str(parse_url($location, PHP_URL_QUERY) ?? '', $query);

        return (string) ($query['code'] ?? '');
    }

    /** @return array{0: string, 1: string} */
    private function fullFlow(): array
    {
        $verifier = $this->generateVerifier();
        $client = OAuthClient::factory()->create();
        $code = $this->completeAuthorize($client, $verifier);

        $response = $this->postJson('/oauth/token', [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'client_id' => $client->client_id,
            'redirect_uri' => $client->redirect_uris[0],
            'code_verifier' => $verifier,
        ]);

        return [$response->json('access_token'), $response->json('refresh_token')];
    }
}
