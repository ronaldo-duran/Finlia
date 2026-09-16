<?php

declare(strict_types=1);

namespace App\Http\Requests\Tour;

use App\Enums\TourStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Da una guía por vista (ADR-0045).
 *
 * Solo viaja CÓMO terminó. La versión no se acepta desde la petición: la pone
 * el servidor desde el registro, o cualquiera podría declararse al día con una
 * versión inventada y no volver a recibir novedades nunca.
 */
class StoreTourViewRequest extends FormRequest
{
    /** El acuse es siempre del propio autenticado (UserPolicy). */
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->user()) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(TourStatus::class)],
        ];
    }
}
