<?php

declare(strict_types=1);

namespace App\Http\Requests\Report;

use App\Enums\ReportFormat;
use App\Enums\ReportPeriod;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida los parámetros de query de los reportes (Épica 8): período
 * comparables y, en la exportación, el formato.
 *
 * No hay household_id que validar: el hogar sale del activo en sesión,
 * nunca de la petición.
 */
class ReportRequest extends FormRequest
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
            'period' => ['nullable', 'in:'.implode(',', array_column(ReportPeriod::cases(), 'value'))],
            'format' => ['nullable', 'in:'.implode(',', array_column(ReportFormat::cases(), 'value'))],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'period.in' => 'El período del reporte no es válido.',
            'format.in' => 'El formato de exportación no es válido.',
        ];
    }

    /**
     * Período solicitado (o el default: mes actual). Nunca null, así el
     * controlador y la vista no repiten el fallback.
     */
    public function period(): ReportPeriod
    {
        return ReportPeriod::tryFrom((string) $this->query('period')) ?? ReportPeriod::Month;
    }

    /**
     * Formato de exportación solicitado. Hoy solo CSV (Épica 8); el enum
     * ReportFormat es el seam al que se añadirá PDF.
     *
     * (No se llama format(): choca con Request::format() de Symfony.)
     */
    public function exportFormat(): ReportFormat
    {
        return ReportFormat::tryFrom((string) $this->query('format')) ?? ReportFormat::Csv;
    }
}
