<?php

declare(strict_types=1);

namespace App\Http\Controllers\OAuth;

use App\Http\Controllers\Controller;
use App\Services\Mcp\OAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Dynamic Client Registration (RFC 7591) sin autenticación.
 *
 * Claude.ai y ChatGPT invocan este endpoint cuando el usuario añade el
 * conector: envían client_name y redirect_uris, aquí emitimos un client_id
 * público. No devolvemos secret porque el flujo usa PKCE.
 */
class ClientRegistrationController extends Controller
{
    public function __invoke(Request $request, OAuthService $oauth): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'client_name' => ['required', 'string', 'max:120'],
            'redirect_uris' => ['required', 'array', 'min:1', 'max:10'],
            'redirect_uris.*' => ['required', 'url:https,http', 'max:500'],
            'client_uri' => ['nullable', 'url:https', 'max:500'],
            'logo_uri' => ['nullable', 'url:https', 'max:500'],
            'software_id' => ['nullable', 'string', 'max:80'],
            'software_version' => ['nullable', 'string', 'max:80'],
            'token_endpoint_auth_method' => ['nullable', 'in:none'],
            'grant_types' => ['nullable', 'array'],
            'response_types' => ['nullable', 'array'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'invalid_client_metadata',
                'error_description' => $validator->errors()->first(),
            ], 400);
        }

        $data = $validator->validated();

        $client = $oauth->registerClient(
            clientName: $data['client_name'],
            redirectUris: $data['redirect_uris'],
            clientUri: $data['client_uri'] ?? null,
            logoUri: $data['logo_uri'] ?? null,
            softwareId: $data['software_id'] ?? null,
            softwareVersion: $data['software_version'] ?? null,
        );

        return response()->json([
            'client_id' => $client->client_id,
            'client_id_issued_at' => $client->created_at?->timestamp,
            'client_name' => $client->client_name,
            'redirect_uris' => $client->redirect_uris,
            'grant_types' => $client->grant_types,
            'response_types' => $client->response_types,
            'token_endpoint_auth_method' => $client->token_endpoint_auth_method,
            'scope' => $client->scope,
        ], 201);
    }
}
