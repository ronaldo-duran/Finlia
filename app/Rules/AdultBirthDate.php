<?php

declare(strict_types=1);

namespace App\Rules;

use Carbon\Carbon;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Throwable;

/**
 * Fecha de nacimiento de una persona mayor de edad (Plan 04, ADR-0032):
 * fecha real del pasado (no anterior a 1900, no futura) y con 18 años
 * cumplidos hoy o antes. Finlia maneja las finanzas y datos de un hogar
 * completo: 18+ simplifica el cumplimiento de la Ley 1581 (nada de
 * consentimiento parental) y el público real (jefes de hogar) lo cumple.
 *
 * Una sola fuente para registro y perfil: el corte está aquí, no repetido
 * en cada Form Request. El FORMATO lo valida la regla 'date' que la
 * acompaña; esta regla solo juzga la semántica de la fecha.
 */
class AdultBirthDate implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            $fecha = Carbon::parse($value)->startOfDay();
        } catch (Throwable) {
            return; // formato inválido: que lo reporte la regla 'date'
        }

        $hoy = today()->startOfDay();

        if ($fecha->lessThan(Carbon::create(1900, 1, 1))) {
            $fail('La :attribute no parece una fecha real.');

            return;
        }

        if ($fecha->greaterThan($hoy)) {
            $fail('La :attribute no puede ser una fecha futura.');

            return;
        }

        // Inclusiva: quien cumple 18 años HOY entra.
        if ($fecha->greaterThan($hoy->copy()->subYears(18))) {
            $fail('Debes ser mayor de edad (18 años) para usar Finlia.');
        }
    }
}
