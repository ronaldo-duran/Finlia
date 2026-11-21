<?php

declare(strict_types=1);

namespace App\Http\Controllers\OAuth;

use App\Http\Controllers\Controller;
use App\Models\Household;
use App\Services\Mcp\OAuthService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Authorization endpoint OAuth 2.1 con pantalla de consentimiento.
 *
 * GET muestra al usuario qué cliente pide acceso y a qué hogar; POST emite
 * el authorization_code y redirige al cliente. PKCE S256 es obligatorio.
 */
class AuthorizeController extends Controller
{
    public function show(Request $request, OAuthService $oauth): View|RedirectResponse
    {
        $params = $this->validateAuthorizeParams($request);
        if ($params instanceof RedirectResponse) {
            return $params;
        }

        $client = $oauth->findClient($params['client_id']);
        if ($client === null || ! $client->hasRedirectUri($params['redirect_uri'])) {
            abort(400, 'client_id o redirect_uri inválidos');
        }

        $user = $request->user();
        $households = $user->households()->orderBy('name')->get();

        return view('oauth.authorize', [
            'client' => $client,
            'households' => $households,
            'activeHousehold' => active_household(),
            'params' => $params,
        ]);
    }

    public function approve(Request $request, OAuthService $oauth): RedirectResponse
    {
        $params = $this->validateAuthorizeParams($request);
        if ($params instanceof RedirectResponse) {
            return $params;
        }

        $client = $oauth->findClient($params['client_id']);
        if ($client === null || ! $client->hasRedirectUri($params['redirect_uri'])) {
            abort(400, 'client_id o redirect_uri inválidos');
        }

        if ($request->input('decision') === 'deny') {
            return redirect()->to($this->appendQuery($params['redirect_uri'], [
                'error' => 'access_denied',
                'error_description' => 'El usuario denegó el acceso',
                'state' => $params['state'],
            ]));
        }

        $householdId = (int) $request->input('household_id');
        $household = Household::query()
            ->whereKey($householdId)
            ->whereHas('members', fn ($q) => $q->whereKey($request->user()->id))
            ->first();

        if ($household === null) {
            abort(403, 'No puedes autorizar un hogar al que no perteneces');
        }

        $code = $oauth->issueAuthorizationCode(
            client: $client,
            user: $request->user(),
            household: $household,
            redirectUri: $params['redirect_uri'],
            codeChallenge: $params['code_challenge'],
            codeChallengeMethod: $params['code_challenge_method'],
            scope: $params['scope'],
        );

        return redirect()->to($this->appendQuery($params['redirect_uri'], [
            'code' => $code,
            'state' => $params['state'],
        ]));
    }

    /**
     * @return array<string, string>|RedirectResponse
     */
    private function validateAuthorizeParams(Request $request): array|RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'response_type' => ['required', 'in:code'],
            'client_id' => ['required', 'string'],
            'redirect_uri' => ['required', 'url:https,http', 'max:500'],
            'code_challenge' => ['required', 'string', 'min:43', 'max:128'],
            'code_challenge_method' => ['required', 'in:S256'],
            'scope' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            abort(400, 'Parámetros OAuth inválidos: '.$validator->errors()->first());
        }

        $data = $validator->validated();
        $data['scope'] = $data['scope'] ?? 'mcp:read';
        $data['state'] = $data['state'] ?? '';

        return $data;
    }

    /**
     * @param  array<string, string>  $params
     */
    private function appendQuery(string $url, array $params): string
    {
        $params = array_filter($params, fn ($v) => $v !== null && $v !== '');
        $separator = str_contains($url, '?') ? '&' : '?';

        return $url.$separator.http_build_query($params);
    }
}
