<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

/**
 * Valida el restablecimiento efectivo de la contraseña con un token.
 */
class StoreNewPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // ruta pública bajo middleware 'guest'
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email:rfc', 'max:150'],
            'password' => ['required', 'string', 'confirmed', PasswordRule::min(8)->max(72)],
        ];
    }

    /**
     * Restablece la contraseña vía broker de Laravel.
     *
     * @throws ValidationException
     */
    public function resetPassword(): void
    {
        $status = Password::reset(
            $this->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }
    }
}
