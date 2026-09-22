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
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email:rfc', 'max:150'],
            'role' => ['sometimes', Rule::in([HouseholdRole::Member->value])],
        ];
    }

    public function invitedEmail(): string
    {
        return $this->string('email')->trim()->lower()->toString();
    }

    public function invitedRole(): HouseholdRole
    {
        return HouseholdRole::Member;
    }
}
