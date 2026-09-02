<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\StoreRegistrationRequest;
use App\Models\User;
use App\Services\HouseholdService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
     * Crea un usuario con su hogar personal inicial y le envía el correo
     * de verificación (Plan 01, ADR-0029): no entra a la app hasta
     * confirmar su correo — el middleware 'verified' bloquea todo.
     */
    public function store(StoreRegistrationRequest $request): RedirectResponse
    {
        $email = $request->string('email')->trim()->lower()->toString();

        [$user, $household] = DB::transaction(function () use ($request, $email): array {
            // Anti-squatting (Plan 01, regla 6): si ese correo quedó
            // registrado pero nunca verificado, el fantasma no puede
            // bloquear a su dueño real. Es inerte por construcción (sin
            // verificar no se crea ningún dato: el middleware 'verified'
            // lo impide), así que se borra con su hogar vacío y el correo
            // queda libre. Las FK en cascada limpian hogar y pivote.
            User::query()
                ->where('email', $email)
                ->whereNull('email_verified_at')
                ->delete();

            $user = User::create([
                'name' => $request->string('name')->trim()->toString(),
                'email' => $email,
                // El cast 'hashed' del modelo cifra el valor.
                'password' => $request->string('password')->toString(),
                // Fecha de nacimiento validada como 18+ (Plan 04, ADR-0032).
                'birth_date' => $request->date('birth_date')->toDateString(),
            ]);

            // Todo usuario arranca con un hogar personal propio (Épica 2),
            // así la app siempre tiene un hogar activo operativo.
            $household = $this->householdService->createHousehold(
                ownerId: $user->id,
                name: 'Mi hogar',
            );

            return [$user, $household];
        });

        // El correo sale DESPUÉS del commit: un SMTP lento no debe sostener
        // la transacción abierta (y si falla, el registro ya quedó bien).
        $user->sendEmailVerificationNotification();

        Auth::login($user);

        $request->session()->regenerate();
        session(['household_id' => $household->id]);

        return redirect()
            ->route('verification.notice')
            ->with('status', __('¡Cuenta creada! Te enviamos un enlace de confirmación a tu correo.'));
    }
}
