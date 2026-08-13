<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\StoreRegistrationRequest;
use App\Models\User;
use App\Services\HouseholdService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function __construct(private readonly HouseholdService $householdService) {}

    /**
     * Muestra el formulario de registro.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Crea un usuario, su hogar personal inicial e inicia su sesión.
     *
     * @throws ValidationException
     */
    public function store(StoreRegistrationRequest $request): RedirectResponse
    {
        $user = User::create([
            'name' => $request->string('name')->trim()->toString(),
            'email' => $request->string('email')->trim()->lower()->toString(),
            // El cast 'hashed' del modelo cifra el valor.
            'password' => $request->string('password')->toString(),
        ]);

        // Todo usuario arranca con un hogar personal propio (Épica 2),
        // así la app siempre tiene un hogar activo operativo.
        $household = $this->householdService->createHousehold(
            ownerId: $user->id,
            name: 'Mi hogar',
        );

        Auth::login($user);

        $request->session()->regenerate();
        session(['household_id' => $household->id]);

        return redirect()
            ->route('dashboard')
            ->with('status', __('¡Bienvenido a Finlia, :name!', ['name' => $user->name]));
    }
}
