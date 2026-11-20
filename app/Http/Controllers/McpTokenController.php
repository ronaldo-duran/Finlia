<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\McpToken\StoreMcpTokenRequest;
use App\Models\McpToken;
use App\Services\Mcp\McpTokenService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Gestión en /perfil/mcp de los tokens MCP del usuario (ADR-0049).
 *
 * El token en claro se muestra UNA sola vez tras emitirlo, en el flash de la
 * sesión; cualquier otra vista ve solo nombre, hogar, abilities y últimos
 * timestamps. La revocación es definitiva (soft-delete lógico via revoked_at).
 */
class McpTokenController extends Controller
{
    public function __construct(private readonly McpTokenService $tokens) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $this->authorize('viewAny', McpToken::class);

        $tokens = McpToken::query()
            ->where('user_id', $user->id)
            ->with('household:id,name')
            ->orderByDesc('id')
            ->get();

        return view('profile.mcp.index', [
            'tokens' => $tokens,
            'activeHousehold' => active_household(),
        ]);
    }

    public function store(StoreMcpTokenRequest $request): RedirectResponse
    {
        $user = $request->user();
        $household = active_household();

        abort_if($household === null, 422, 'No hay un hogar activo al que atar el token.');

        $expiresAt = $request->integer('expires_in_days') > 0
            ? now()->addDays($request->integer('expires_in_days'))
            : null;

        $result = $this->tokens->issue(
            user: $user,
            household: $household,
            name: $request->string('name')->toString(),
            abilities: [McpToken::ABILITY_READ],
            expiresAt: $expiresAt,
        );

        return redirect()
            ->route('profile.mcp.index')
            ->with('mcp_new_token', $result['plain'])
            ->with('mcp_new_token_id', $result['token']->id)
            ->with('status', 'Token creado. Cópialo ahora: no volverá a mostrarse.');
    }

    public function destroy(Request $request, McpToken $mcpToken): RedirectResponse
    {
        $this->authorize('delete', $mcpToken);

        $this->tokens->revoke($mcpToken);

        return redirect()
            ->route('profile.mcp.index')
            ->with('status', 'Token revocado.');
    }
}
