<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\View\View;

/**
 * Página pública "Tus datos y Finlia" (Plan 06, ADR-0034).
 * Accesible sin cuenta — también para quien evalúa antes de registrarse.
 */
class DataPolicyController extends Controller
{
    public function show(): View
    {
        return view('data-policy.show');
    }
}
