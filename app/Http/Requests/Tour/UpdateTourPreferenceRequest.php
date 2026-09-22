<?php

declare(strict_types=1);

namespace App\Http\Requests\Tour;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Enciende o apaga las guías para el usuario autenticado (ADR-0045).
 *
 * Lo usan dos sitios: el «No mostrar más guías» del propio globo y el
 * interruptor de /perfil. Es la misma decisión, así que es el mismo endpoint.
 */
class UpdateTourPreferenceRequest extends FormRequest
{
    /** La preferencia es siempre del propio autenticado (UserPolicy). */
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->user()) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'enabled' => ['nullable', 'boolean'],
        ];
    }
}
