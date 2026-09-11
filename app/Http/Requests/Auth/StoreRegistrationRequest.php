<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Models\HouseholdInvitation;
use App\Rules\AdultBirthDate;
use App\Services\HouseholdService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Valida el alta de un usuario (registro).
 * No acepta campos ajenos al alta (p. ej. roles o household_id).
 *
 * birth_date es obligatorio y 18+ (Plan 04, ADR-0032); región y género NO
 * se piden aquí — el perfil es suficiente (menos fricción de entrada).
 */
class StoreRegistrationRequest extends FormRequest
{
    private HouseholdInvitation|false|null $invitation = false;

    public function authorize(): bool
    {
        return true; // ruta pública bajo middleware 'guest'
    }

    /**
     * La invitación desde la que se registra, si llegó por su enlace y sigue
     * vigente (ADR-0039). El token vive en la sesión, nunca en el formulario.
     */
    public function invitation(): ?HouseholdInvitation
    {
        if ($this->invitation === false) {
            $token = $this->session()->get('invitation_token');

            $this->invitation = is_string($token)
                ? app(HouseholdService::class)->findAcceptableInvitation($token)
                : null;
        }

        return $this->invitation;
    }

    /**
     * Desde una invitación, el correo lo dicta la invitación: el campo del
     * formulario es de solo lectura, pero el servidor no se fía de él.
     */
    protected function prepareForValidation(): void
    {
        $invitation = $this->invitation();

        if ($invitation !== null) {
            $this->merge(['email' => $invitation->email]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            // Un correo solo cuenta como tomado si está VERIFICADO. Un
            // registro sin verificar es un fantasma que el propio registro
            // reclama (anti-squatting, Plan 01): el dueño real del correo
            // nunca ve "ya está registrado".
            'email' => [
                'required',
                'string',
                'email:rfc',
                'max:150',
                Rule::unique('users', 'email')->whereNotNull('email_verified_at'),
            ],
            'password' => ['required', 'string', 'min:8', 'max:72', 'confirmed'],
            'birth_date' => ['required', 'date', new AdultBirthDate],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['birth_date' => 'fecha de nacimiento'];
    }
}
