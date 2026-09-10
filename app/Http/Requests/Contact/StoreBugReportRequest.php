<?php

declare(strict_types=1);

namespace App\Http\Requests\Contact;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Reporte de error desde dentro de la aplicación. Exige sesión iniciada.
 *
 * Por eso NO pide nombre ni correo: ya se conocen. Y por eso puede adjuntar
 * contexto técnico solo, que es lo que convierte un "no me funciona" en algo
 * accionable.
 *
 * El contexto del navegador (ruta, viewport) llega del formulario y es, por
 * definición, manipulable: se valida y se recorta, y nunca se usa para decidir
 * nada — solo para leerlo.
 */
class StoreBugReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'min:20', 'max:3000'],
            'origen' => ['nullable', 'string', 'max:255'],
            'viewport' => ['nullable', 'string', 'max:20'],
            'sitio_web' => ['nullable', 'max:0'],
        ];
    }

    /**
     * Contexto técnico del reporte.
     *
     * Se arma aquí y no en el Service porque sale de la petición HTTP, que es
     * justo lo que un Service no debe tocar (ADR-0010).
     *
     * @return array<string, string>
     */
    public function context(): array
    {
        return array_filter([
            'Versión' => (string) config('finlia.version'),
            'Pantalla' => $this->string('origen')->trim()->limit(255)->toString(),
            'Viewport' => $this->string('viewport')->trim()->limit(20)->toString(),
            'Navegador' => substr((string) $this->userAgent(), 0, 255),
        ], fn (string $valor) => $valor !== '');
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'body.min' => 'Cuéntanos qué pasó con un poco más de detalle: al menos 20 caracteres.',
            'sitio_web.max' => 'No pudimos procesar el envío.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['body' => 'descripción'];
    }
}
