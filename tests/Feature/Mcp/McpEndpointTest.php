<?php

declare(strict_types=1);

namespace Tests\Feature\Mcp;

use App\Models\Account;
use App\Models\Expense;
use App\Models\Household;
use App\Models\McpToken;
use App\Models\User;
use App\Services\HouseholdService;
use App\Services\Mcp\McpTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Pruebas de la frontera HTTP del servidor MCP (ADR-0049): autenticación,
 * aislamiento multi-hogar y despacho de métodos JSON-RPC.
 */
class McpEndpointTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Household $household;

    private string $plain;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->household = app(HouseholdService::class)->createHousehold($this->owner->id, 'Hogar de pruebas');

        $result = app(McpTokenService::class)->issue(
            user: $this->owner,
            household: $this->household,
            name: 'Claude Desktop',
            abilities: [McpToken::ABILITY_READ],
        );
        $this->plain = $result['plain'];
    }

    private function rpc(array $payload, ?string $token = null): TestResponse
    {
        return $this->postJson('/mcp', $payload, [
            'Authorization' => 'Bearer '.($token ?? $this->plain),
        ]);
    }

    public function test_initialize_devuelve_capabilities_y_toca_last_used(): void
    {
        $response = $this->rpc(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize']);

        $response->assertOk();
        $response->assertJsonPath('result.protocolVersion', '2025-06-18');
        $response->assertJsonPath('result.serverInfo.name', 'finlia-mcp');
        $response->assertJsonStructure(['result' => ['capabilities' => ['tools']]]);

        $token = McpToken::first();
        $this->assertNotNull($token->last_used_at, 'last_used_at debe actualizarse');
    }

    public function test_sin_authorization_devuelve_401(): void
    {
        $response = $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize']);

        $response->assertStatus(401);
        $response->assertJsonPath('error.code', -32000);
    }

    public function test_token_revocado_devuelve_401(): void
    {
        $token = McpToken::first();
        app(McpTokenService::class)->revoke($token);

        $response = $this->rpc(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize']);

        $response->assertStatus(401);
    }

    public function test_token_expirado_devuelve_401(): void
    {
        McpToken::query()->update(['expires_at' => now()->subDay()]);

        $response = $this->rpc(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize']);

        $response->assertStatus(401);
    }

    public function test_tools_list_devuelve_las_tres_tools_de_lectura(): void
    {
        $response = $this->rpc(['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list']);

        $response->assertOk();
        $names = collect($response->json('result.tools'))->pluck('name')->all();
        $this->assertEqualsCanonicalizing(
            ['get_available_money', 'get_budget_summary', 'list_recent_movements'],
            $names,
        );
    }

    public function test_get_available_money_devuelve_el_cupo_del_hogar_del_token(): void
    {
        Account::factory()->create([
            'household_id' => $this->household->id,
            'current_balance' => 500000,
        ]);

        $response = $this->rpc([
            'jsonrpc' => '2.0', 'id' => 3, 'method' => 'tools/call',
            'params' => ['name' => 'get_available_money', 'arguments' => []],
        ]);

        $response->assertOk();
        $response->assertJsonPath('result.structuredContent.household', 'Hogar de pruebas');
        $this->assertIsNumeric($response->json('result.structuredContent.daily_allowance'));
        $this->assertFalse($response->json('result.isError'));
    }

    public function test_el_token_siempre_opera_sobre_su_hogar_aunque_el_usuario_pertenezca_a_otro(): void
    {
        $otroHogar = app(HouseholdService::class)->createHousehold($this->owner->id, 'Hogar Secundario');

        $cuentaDelTokenHogar = Account::factory()->create([
            'household_id' => $this->household->id,
            'current_balance' => 100000,
        ]);
        Expense::factory()->create([
            'household_id' => $this->household->id,
            'account_id' => $cuentaDelTokenHogar->id,
            'user_id' => $this->owner->id,
            'description' => 'GASTO-DEL-HOGAR-DEL-TOKEN',
            'amount' => 15000,
        ]);

        $cuentaDelOtro = Account::factory()->create([
            'household_id' => $otroHogar->id,
            'current_balance' => 777000,
        ]);
        Expense::factory()->create([
            'household_id' => $otroHogar->id,
            'account_id' => $cuentaDelOtro->id,
            'user_id' => $this->owner->id,
            'description' => 'GASTO-DEL-HOGAR-AJENO',
            'amount' => 99999,
        ]);

        $response = $this->rpc([
            'jsonrpc' => '2.0', 'id' => 4, 'method' => 'tools/call',
            'params' => ['name' => 'list_recent_movements', 'arguments' => ['limit' => 50]],
        ]);

        $response->assertOk();
        $descripciones = collect($response->json('result.structuredContent.movements'))
            ->pluck('description')
            ->all();

        $this->assertContains('GASTO-DEL-HOGAR-DEL-TOKEN', $descripciones);
        $this->assertNotContains('GASTO-DEL-HOGAR-AJENO', $descripciones,
            'El token nunca debe exponer movimientos de otro hogar aunque el usuario pertenezca a él.');
    }

    public function test_token_sin_la_ability_requerida_rechaza_la_tool(): void
    {
        McpToken::query()->update(['abilities' => []]);

        $response = $this->rpc([
            'jsonrpc' => '2.0', 'id' => 5, 'method' => 'tools/call',
            'params' => ['name' => 'get_available_money', 'arguments' => []],
        ]);

        $response->assertOk();
        $this->assertSame(-32001, $response->json('error.code'));
    }

    public function test_tool_desconocida_devuelve_error(): void
    {
        $response = $this->rpc([
            'jsonrpc' => '2.0', 'id' => 6, 'method' => 'tools/call',
            'params' => ['name' => 'borrar_todo_el_hogar', 'arguments' => []],
        ]);

        $response->assertOk();
        $this->assertSame(-32601, $response->json('error.code'));
    }

    public function test_jsonrpc_sin_method_devuelve_invalid_request(): void
    {
        $response = $this->rpc(['jsonrpc' => '2.0', 'id' => 99]);

        $response->assertOk();
        $this->assertSame(-32600, $response->json('error.code'));
    }

    public function test_usuario_expulsado_del_hogar_pierde_el_token(): void
    {
        $miembro = User::factory()->create();
        $miembro->households()->attach($this->household->id, [
            'role' => 'member',
            'joined_at' => now(),
        ]);

        $tokenDelMiembro = app(McpTokenService::class)->issue(
            user: $miembro,
            household: $this->household,
            name: 'Del miembro',
            abilities: [McpToken::ABILITY_READ],
        );

        app(HouseholdService::class)->removeMember($this->household, $miembro);

        $response = $this->postJson('/mcp', [
            'jsonrpc' => '2.0', 'id' => 7, 'method' => 'initialize',
        ], [
            'Authorization' => 'Bearer '.$tokenDelMiembro['plain'],
        ]);

        $response->assertStatus(401);
    }
}
