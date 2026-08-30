<?php

declare(strict_types=1);

namespace App\Http\Requests\Profile;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Arranca el cambio de correo (Plan 02): no toca users.email — solo deja
 * el pendiente y envía la confirmación a la bandeja nueva.
 */
class UpdateEmailRequest extends FormRequest
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
            'email' => [
                'required',
                'string',
                'email:rfc',
                'max:150',
                // Solo un correo VERIFICADO cuenta como tomado (anti-
                // squatting, Plan 01). Los fantasmas sin verificar se
                // reclaman al confirmar, igual que en el registro.
                Rule::unique('users', 'email')
                    ->whereNotNull('email_verified_at')
                    ->ignore($this->user()->id),
                // Pendiente de confirmación por OTRO usuario.
                Rule::unique('users', 'pending_email')->ignore($this->user()->id),
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (strtolower(trim((string) $value)) === strtolower((string) $this->user()->email)) {
                        $fail(__('Ese ya es tu correo actual.'));
                    }
                },
            ],
        ];
    }
}
