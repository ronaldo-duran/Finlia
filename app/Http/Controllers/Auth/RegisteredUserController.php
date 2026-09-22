<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\StoreRegistrationRequest;
use App\Models\User;
use App\Services\HouseholdService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function __construct(private readonly HouseholdService $householdService) {}

    /**
     * Muestra el formulario de registro.
     */
    public function create(Request $request): View
    {
        $token = $request->session()->get('invitation_token');

        return view('auth.register', [
            'invitation' => is_string($token)
                ? $this->householdService->findAcceptableInvitation($token)
                : null,
        ]);
    }

    /**
     * Crea un usuario —con su hogar personal, salvo que venga de una
     * invitación— y le envía el correo de verificación (Plan 01, ADR-0029):
     * no entra a la app hasta confirmar su correo.
     */
    public function store(StoreRegistrationRequest $request): RedirectResponse
    {
        $email = $request->string('email')->trim()->lower()->toString();
        $invitation = $request->invitation();

        [$user, $household] = DB::transaction(function () use ($request, $email, $invitation): array {
            User::query()
                ->where('email', $email)
                ->whereNull('email_verified_at')
                ->delete();

            $user = User::create([
                'name' => $request->string('name')->trim()->toString(),
                'email' => $email,
                'password' => $request->string('password')->toString(),
                'birth_date' => $request->date('birth_date')->toDateString(),
            ]);

            $household = $invitation === null
                ? $this->householdService->createHousehold(ownerId: $user->id, name: 'Mi hogar')
                : null;

            return [$user, $household];
        });

        $user->sendEmailVerificationNotification();

        Auth::login($user);

        $request->session()->regenerate();

        if ($household !== null) {
            session(['household_id' => $household->id]);
        }

        if ($invitation !== null) {
            $request->session()->forget(['invitation_token', 'url.intended']);

            return redirect()
                ->route('verification.notice')
                ->with('status', __('¡Cuenta creada! Confirma tu correo y entrarás a ":name".', [
                    'name' => $invitation->household->name,
                ]));
        }

        return redirect()
            ->route('verification.notice')
            ->with('status', __('¡Cuenta creada! Te enviamos un enlace de confirmación a tu correo.'));
    }
}
