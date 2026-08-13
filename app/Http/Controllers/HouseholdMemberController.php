<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Household;
use App\Models\User;
use App\Services\HouseholdService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class HouseholdMemberController extends Controller
{
    public function __construct(private readonly HouseholdService $service) {}

    /**
     * Expulsa a un miembro (el owner no puede ser expulsado).
     */
    public function destroy(Request $request, Household $household, User $user): RedirectResponse
    {
        $this->authorize('manageMembers', $household);

        try {
            $this->service->removeMember($household, $user);
        } catch (ValidationException $e) {
            return redirect()
                ->route('households.show', $household)
                ->withErrors($e->errors());
        }

        return redirect()
            ->route('households.show', $household)
            ->with('status', __('Miembro eliminado del hogar.'));
    }
}
