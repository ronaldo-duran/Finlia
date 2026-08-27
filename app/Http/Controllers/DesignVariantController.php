<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

/**
 * Alterna la variante visual del rediseño mobile-first (Épica 10,
 * adelantado): "a" (Enfoque) o "b" (Control). Preferencia de UI en
 * sesión, sin lógica financiera —por eso no hay Service ni Policy.
 */
class DesignVariantController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $variant = $request->string('variant')->value();

        Session::put('design_variant', in_array($variant, ['a', 'b'], true) ? $variant : 'a');

        return redirect()->back();
    }
}
