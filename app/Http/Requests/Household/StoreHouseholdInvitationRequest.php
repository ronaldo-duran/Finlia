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
            'role' => ['required', Rule::enum(HouseholdRole::class)],
        ];
    }

    public function invitedEmail(): string
    {
        return $this->string('email')->trim()->lower()->toString();
    }

    public function invitedRole(): HouseholdRole
    {
        /** @var HouseholdRole $role */
        $role = $this->enum('role', HouseholdRole::class);

        return $role;
    }
}
