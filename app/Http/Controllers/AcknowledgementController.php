<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\AcknowledgementKey;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * «Entendido»: el usuario da por leído un aviso y deja de verlo completo
 * (ADR-0024).
 */
class AcknowledgementController extends Controller
{
    public function store(Request $request, string $key): RedirectResponse
    {
        $acknowledgement = AcknowledgementKey::tryFrom($key);

        abort_if($acknowledgement === null, 404);

        $request->user()->acknowledge($acknowledgement);

        return back();
    }
}
