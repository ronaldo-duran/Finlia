<?php

declare(strict_types=1);

namespace App\Http\Requests\Household;

use App\Enums\HouseholdRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Valida el envío de una invitación a un hogar.
 */
class StoreHouseholdInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // la autorización (ser owner) la resuelve la Policy
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email:rfc', 'max:150'],
            // Solo se puede invitar como MIEMBRO. La titularidad (owner) se
            // decide por households.owner_id, no por el pivot; aceptar
            // role=owner creaba un "administrador fantasma" (un pivot owner
            // sin poder real) que confundía la UI y dejaba residuos al purgar.
            'role' => ['sometimes', Rule::in([HouseholdRole::Member->value])],
        ];
    }

    public function invitedEmail(): string
    {
        return $this->string('email')->trim()->lower()->toString();
    }

    public function invitedRole(): HouseholdRole
    {
        // Las invitaciones siempre son de miembro (ver rules()).
        return HouseholdRole::Member;
    }
}
