<?php

declare(strict_types=1);

namespace App\Http\Controllers\OAuth;

use App\Http\Controllers\Controller;
use App\Models\OAuthAccessToken;
use App\Models\OAuthRefreshToken;
use App\Services\Mcp\OAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Token revocation endpoint (RFC 7009) para clientes públicos.
 *
 * Siempre devuelve 200, incluso con tokens desconocidos, como manda el RFC:
 * el objetivo es no dar pistas sobre qué tokens existen.
 */
class RevocationController extends Controller
{
    public function __invoke(Request $request, OAuthService $oauth): JsonResponse
    {
        $token = (string) $request->input('token', '');

        if ($token === '') {
            return response()->json(null, 200);
        }

        $hash = $oauth->hash($token);

        if (Str::startsWith($token, OAuthAccessToken::PLAIN_PREFIX)) {
            OAuthAccessToken::query()
                ->where('token_hash', $hash)
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);
        } elseif (Str::startsWith($token, OAuthRefreshToken::PLAIN_PREFIX)) {
            OAuthRefreshToken::query()
                ->where('token_hash', $hash)
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);
        }

        return response()->json(null, 200);
    }
}
