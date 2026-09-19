<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Household\StoreHouseholdRequest;
use App\Http\Requests\Household\UpdateHouseholdRequest;
use App\Models\Household;
use App\Services\HouseholdService;
use App\Services\SubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HouseholdController extends Controller
{
    public function __construct(
        private readonly HouseholdService $service,
        private readonly SubscriptionService $subscriptions,
    ) {}

    /**
     * Lista los hogares del usuario (selector).
     */
    public function index(Request $request): View
    {
        $households = $request->user()
            ->households()
            ->withCount('members')
            ->get();

        return view('households.index', [
            'households' => $households,
            'activeHouseholdId' => active_household_id(),
        ]);
    }

    public function create(Request $request): View|RedirectResponse
    {
        if (! $this->subscriptions->canUserCreateHousehold($request->user())) {
            return redirect()
                ->route('households.index')
                ->with('status', __('Tu plan gratuito permite un solo hogar. Pasa a Premium para crear más.'));
        }

        return view('households.create');
    }

    public function store(StoreHouseholdRequest $request): RedirectResponse
    {
        if (! $this->subscriptions->canUserCreateHousehold($request->user())) {
            return redirect()
                ->route('households.index')
                ->with('status', __('Tu plan gratuito permite un solo hogar. Pasa a Premium para crear más.'));
        }

        $data = $request->validatedData();

        $household = $this->service->createHousehold(
            ownerId: $request->user()->id,
            name: $data['name'],
            currency: $data['currency'],
            timezone: $data['timezone'],
        );

        // El hogar recién creado pasa a ser el activo.
        session(['household_id' => $household->id]);
        app()->forgetInstance('finlia.active_household');

        return redirect()
            ->route('households.show', $household)
            ->with('status', __('Hogar ":name" creado.', ['name' => $household->name]));
    }

    /**
     * Configuración del hogar: datos, miembros e invitaciones.
     */
    public function show(Request $request, Household $household): View
    {
        $this->authorize('view', $household);

        $household->load([
            'owner',
            'members' => fn ($q) => $q->orderByPivot('joined_at'),
            'invitations' => fn ($q) => $q->latest(),
        ]);

        return view('households.show', [
            'household' => $household,
            'invitationLink' => session('invitation_link'),
            'invitationEmail' => session('invitation_email'),
        ]);
    }

    public function edit(Request $request, Household $household): View
    {
        $this->authorize('update', $household);

        return view('households.edit', ['household' => $household]);
    }

    public function update(UpdateHouseholdRequest $request, Household $household): RedirectResponse
    {
        $this->authorize('update', $household);

        $this->service->updateHousehold($household, $request->validatedData());

        return redirect()
            ->route('households.show', $household)
            ->with('status', __('Datos del hogar actualizados.'));
    }

    public function destroy(Request $request, Household $household): RedirectResponse
    {
        $this->authorize('delete', $household);

        $household->delete();

        // Si era el hogar activo, se resetea para re-resolver.
        if (active_household_id() === $household->id) {
            session()->forget('household_id');
            app()->forgetInstance('finlia.active_household');
        }

        return redirect()
            ->route('households.index')
            ->with('status', __('Hogar ":name" eliminado.', ['name' => $household->name]));
    }
}
