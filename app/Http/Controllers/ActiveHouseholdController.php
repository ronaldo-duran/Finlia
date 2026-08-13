<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Household;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Cambia el hogar activo del usuario (guardado en sesión).
 */
class ActiveHouseholdController extends Controller
{
    public function __invoke(Request $request, Household $household): RedirectResponse
    {
        $this->authorize('activate', $household);

        session(['household_id' => $household->id]);
        app()->forgetInstance('finlia.active_household');

        return redirect()
            ->intended(route('dashboard'))
            ->with('status', __('Hogar activo: :name', ['name' => $household->name]));
    }
}
