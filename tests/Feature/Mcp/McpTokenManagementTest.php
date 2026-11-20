<?php

declare(strict_types=1);

namespace Tests\Feature\Mcp;

use App\Models\Household;
use App\Models\McpToken;
use App\Models\User;
use App\Services\HouseholdService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * UI en /perfil/mcp: emisión, listado y revocación.
 */
class McpTokenManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Household $household;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->household = app(HouseholdService::class)->createHousehold($this->user->id, 'Hogar');
    }

    public function test_pantalla_mcp_requiere_autenticacion(): void
    {
        $this->get('/perfil/mcp')->assertRedirect('/login');
    }

    public function test_pantalla_mcp_lista_los_tokens_del_usuario(): void
    {
        $this->actingAs($this->user);

        $this->get('/perfil/mcp')
            ->assertOk()
            ->assertSee('Tokens MCP');
    }

    public function test_crear_token_devuelve_el_string_en_claro_una_sola_vez(): void
    {
        $this->actingAs($this->user);

        $response = $this->post('/perfil/mcp', ['name' => 'Mi Claude']);

        $response->assertRedirect('/perfil/mcp');
        $response->assertSessionHas('mcp_new_token');
        $plain = session('mcp_new_token');

        $this->assertStringStartsWith(McpToken::PLAIN_PREFIX, $plain);
        $this->assertSame(1, McpToken::count());

        $firstGet = $this->get('/perfil/mcp');
        $firstGet->assertSee($plain);

        $secondGet = $this->get('/perfil/mcp');
        $secondGet->assertDontSee($plain);
    }

    public function test_crear_token_con_vencimiento(): void
    {
        $this->actingAs($this->user);

        $this->post('/perfil/mcp', [
            'name' => 'Temporal',
            'expires_in_days' => 7,
        ])->assertRedirect();

        $token = McpToken::first();
        $this->assertNotNull($token->expires_at);
        $this->assertTrue($token->expires_at->isFuture());
        $this->assertTrue($token->expires_at->lessThanOrEqualTo(now()->addDays(8)));
    }

    public function test_nombre_es_obligatorio(): void
    {
        $this->actingAs($this->user);

        $this->post('/perfil/mcp', ['name' => ''])
            ->assertSessionHasErrors('name');

        $this->assertSame(0, McpToken::count());
    }

    public function test_revocar_token_propio_lo_marca_como_revocado(): void
    {
        $this->actingAs($this->user);
        $token = McpToken::factory()->create([
            'user_id' => $this->user->id,
            'household_id' => $this->household->id,
        ]);

        $this->delete('/perfil/mcp/'.$token->id)
            ->assertRedirect('/perfil/mcp');

        $this->assertNotNull($token->fresh()->revoked_at);
    }

    public function test_no_se_puede_revocar_un_token_de_otro_usuario(): void
    {
        $otro = User::factory()->create();
        $otroHogar = app(HouseholdService::class)->createHousehold($otro->id, 'Ajeno');
        $tokenAjeno = McpToken::factory()->create([
            'user_id' => $otro->id,
            'household_id' => $otroHogar->id,
        ]);

        $this->actingAs($this->user);

        $this->delete('/perfil/mcp/'.$tokenAjeno->id)->assertForbidden();

        $this->assertNull($tokenAjeno->fresh()->revoked_at);
    }
}
