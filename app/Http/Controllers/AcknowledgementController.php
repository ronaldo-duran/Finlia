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
        // La clave llega en la URL: solo se aceptan las del enum, nunca texto
        // libre. Un valor inventado es un 404, no una fila basura en la tabla.
        $acknowledgement = AcknowledgementKey::tryFrom($key);

        abort_if($acknowledgement === null, 404);

        // El acuse es SIEMPRE del usuario autenticado: no hay forma de marcar
        // el aviso de otra persona, porque el id no viene de la petición.
        $request->user()->acknowledge($acknowledgement);

        return back();
    }
}
