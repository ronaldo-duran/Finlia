<?php

declare(strict_types=1);

namespace App\Http\Requests\Contact;

use App\Enums\ContactReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Formulario público de contacto (finlia.online/contacto).
 *
 * Abierto a cualquiera, con o sin cuenta. El motivo «reporte de error» NO se
 * acepta aquí: ese va por la aplicación y con sesión, para que llegue con el
 * contexto técnico ya adjunto.
 */
class StoreContactMessageRequest extends FormRequest
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
            'reason' => ['required', Rule::in(array_column(ContactReason::public(), 'value'))],
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'body' => ['required', 'string', 'min:20', 'max:3000'],

            // Campo trampa: está oculto por CSS, así que una persona nunca lo
            // rellena y un bot que completa todo el formulario, sí. Es la
            // alternativa barata a ponerle un puzzle a quien sí es humano.
            'sitio_web' => ['nullable', 'max:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'body.min' => 'Cuéntanos un poco más: al menos 20 caracteres.',
            'sitio_web.max' => 'No pudimos procesar el envío.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'reason' => 'motivo',
            'name' => 'nombre',
            'email' => 'correo',
            'body' => 'mensaje',
        ];
    }
}
