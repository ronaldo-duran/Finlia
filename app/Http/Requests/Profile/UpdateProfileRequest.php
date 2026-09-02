<?php

declare(strict_types=1);

namespace App\Http\Requests\Profile;

use App\Enums\ColombianRegion;
use App\Enums\Gender;
use App\Rules\AdultBirthDate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Datos personales del perfil (Plan 02 nombre + Plan 04 demográficos):
 * birth_date (obligatorio, 18+ — completa a los usuarios heredados) y
 * región/género opcionales en listas cerradas (ADR-0032). El propósito de
 * cada dato está declarado en la propia pantalla: nada de "por si acaso".
 */
class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // /perfil solo opera sobre el usuario autenticado (UserPolicy en el controlador)
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'birth_date' => ['required', 'date', new AdultBirthDate],
            'region' => ['nullable', Rule::enum(ColombianRegion::class)],
            'gender' => ['nullable', Rule::enum(Gender::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'birth_date' => 'fecha de nacimiento',
            'region' => 'región',
            'gender' => 'género',
        ];
    }
}
