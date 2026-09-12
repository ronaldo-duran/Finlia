<?php

declare(strict_types=1);

namespace App\Http\Requests\Profile;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Arranca el cambio de correo (Plan 02): no toca users.email — solo deja
 * el pendiente y envía la confirmación a la bandeja nueva.
 *
 * Exige la contraseña actual (current_password): cambiar el correo es el
 * primer paso de un secuestro de cuenta (con el correo nuevo se dispara el
 * reset de contraseña), así que se re-autentica igual que el cambio de
 * contraseña y la eliminación de cuenta. Sin esto, una sesión robada podría
 * apropiarse de la cuenta sin conocer la contraseña.
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
            'current_password' => ['required', 'string', 'current_password:web'],
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

    /**
     * Mensaje claro para la re-autenticación (el genérico de
     * current_password es críptico para quien no sabe de "guards").
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'current_password.current_password' => __('La contraseña actual no coincide.'),
        ];
    }
}
