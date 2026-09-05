<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Household;
use App\Models\User;
use ZipArchive;

/**
 * Exportación completa de los datos de un hogar (Plan 06, ADR-0034).
 *
 * Genera un ZIP con:
 *  - Un CSV por entidad (BOM UTF-8 + separador `;`  — abre correctamente en
 *    Excel Colombia sin configurar nada).
 *  - `finlia.json`: la misma data en JSON para migración técnica.
 *  - `README.txt`: descripción de cada archivo y el formato usado.
 *
 * Reglas de privacidad:
 *  - Solo se exportan los datos del HOGAR ACTIVO del usuario solicitante.
 *  - El CSV `usuario.csv` contiene SOLO los datos del solicitante (nunca
 *    nombres ni correos de otros miembros).
 *  - La contraseña nunca se exporta (un hash no es dato portable, §SECURITY).
 */
class DataExportService
{
    private const DATE_FORMAT = 'd/m/Y';

    /**
     * Construye el ZIP en un archivo temporal y devuelve su ruta.
     * El caller debe borrarlo tras enviarlo al cliente.
     */
    public function buildZip(Household $household, User $user): string
    {
        $data = $this->collect($household, $user);
        $path = tempnam(sys_get_temp_dir(), 'finlia_export_');

        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);

        foreach ($data['csv'] as $filename => $content) {
            $zip->addFromString($filename, $content);
        }

        $zip->addFromString('finlia.json', json_encode($data['json'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $zip->addFromString('README.txt', $this->readme($household));

        $zip->close();

        return $path;
    }

    /**
     * Devuelve la colección de datos lista para CSV y JSON.
     * Se expone para tests sin necesidad de crear un archivo en disco.
     *
     * @return array{csv: array<string,string>, json: array<string,mixed>}
     */
    public function collect(Household $household, User $user): array
    {
        $accounts = $household->accounts()->with('creditCard')->get();
        $categories = $household->categories()->get()->keyBy('id');
        $incomes = $household->incomes()->orderBy('date')->get();
        $expenses = $household->expenses()->orderBy('date')->get();
        $budgets = $household->budgets()->get();
        $recurring = $household->recurringExpenses()->get();
        $debts = $household->debts()->withTrashed()->get();
        $payments = $household->debtPayments()->orderBy('date')->get();
        $refinancings = $debts->flatMap(fn ($d) => $d->refinancings);
        $goals = $household->savingsGoals()->get();
        $contributions = $goals->flatMap(fn ($g) => $g->contributions);
        $reminders = $household->reminders()->get();

        $categoryName = fn (?int $id): string => $id && $categories->has($id)
            ? $categories->get($id)->name
            : '';

        // ---- filas por entidad ----

        $accountRows = $accounts->map(fn ($a) => [
            'id' => $a->id,
            'nombre' => $a->name,
            'tipo' => $a->type?->value ?? '',
            'saldo_inicial' => $this->money($a->initial_balance),
            'saldo_actual' => $this->money($a->current_balance),
            'moneda' => $a->currency ?? 'COP',
            'activa' => $a->is_active ? 'sí' : 'no',
            'notas' => $a->notes ?? '',
            'creada' => $this->date($a->created_at),
        ])->toArray();

        $incomeRows = $incomes->map(fn ($i) => [
            'id' => $i->id,
            'fecha' => $this->date($i->date),
            'monto' => $this->money($i->amount),
            'descripcion' => $i->description ?? '',
            'categoria' => $categoryName($i->category_id),
            'cuenta_id' => $i->account_id ?? '',
            'fuente' => $i->source ?? '',
            'notas' => $i->notes ?? '',
        ])->toArray();

        $expenseRows = $expenses->map(fn ($e) => [
            'id' => $e->id,
            'fecha' => $this->date($e->date),
            'monto' => $this->money($e->amount),
            'descripcion' => $e->description ?? '',
            'categoria' => $categoryName($e->category_id),
            'cuenta_id' => $e->account_id ?? '',
            'metodo_pago' => $e->payment_method?->value ?? '',
            'notas' => $e->notes ?? '',
        ])->toArray();

        $budgetRows = $budgets->map(fn ($b) => [
            'id' => $b->id,
            'ano' => $b->year,
            'mes' => $b->month,
            'periodo' => $b->period?->value ?? '',
            'monto' => $this->money($b->amount),
            'categoria' => $categoryName($b->category_id),
        ])->toArray();

        $recurringRows = $recurring->map(fn ($r) => [
            'id' => $r->id,
            'nombre' => $r->name,
            'monto' => $this->money($r->amount),
            'frecuencia' => $r->frequency?->value ?? '',
            'intervalo' => $r->frequency_interval ?? 1,
            'proxima_fecha' => $this->date($r->next_date),
            'categoria' => $categoryName($r->category_id),
            'activo' => $r->is_active ? 'sí' : 'no',
            'genera_automatico' => $r->auto_generate ? 'sí' : 'no',
            'notas' => $r->notes ?? '',
        ])->toArray();

        $debtRows = $debts->map(fn ($d) => [
            'id' => $d->id,
            'nombre' => $d->name,
            'entidad' => $d->institution ?? '',
            'tipo' => $d->type?->value ?? '',
            'monto_original' => $this->money($d->original_amount),
            'saldo_actual' => $this->money($d->current_balance),
            'tasa_interes' => $d->interest_rate !== null ? $this->decimal((float) $d->interest_rate) : '',
            'tipo_tasa' => $d->interest_rate_type?->value ?? '',
            'pago_minimo' => $d->minimum_payment !== null ? $this->money($d->minimum_payment) : '',
            'pago_planeado' => $d->planned_payment !== null ? $this->money($d->planned_payment) : '',
            'plazo_meses' => $d->term_months ?? '',
            'dia_pago' => $d->due_day ?? '',
            'fecha_inicio' => $this->date($d->start_date),
            'fecha_fin' => $this->date($d->end_date),
            'estado' => $d->status?->value ?? '',
            'notas' => $d->notes ?? '',
        ])->toArray();

        $paymentRows = $payments->map(fn ($p) => [
            'id' => $p->id,
            'deuda_id' => $p->debt_id,
            'fecha' => $this->date($p->date),
            'monto' => $this->money($p->amount),
            'tipo' => $p->type?->value ?? '',
            'notas' => $p->notes ?? '',
        ])->toArray();

        $refinancingRows = $refinancings->map(fn ($r) => [
            'id' => $r->id,
            'deuda_id' => $r->debt_id,
            'saldo_refinanciado' => $this->money($r->refinanced_balance),
            'tasa_interes' => $r->interest_rate !== null ? $this->decimal((float) $r->interest_rate) : '',
            'plazo_meses' => $r->term_months ?? '',
            'cuota' => $r->installment !== null ? $this->money($r->installment) : '',
            'fecha_inicio' => $this->date($r->start_date),
            'notas' => $r->notes ?? '',
        ])->toArray();

        $goalRows = $goals->map(fn ($g) => [
            'id' => $g->id,
            'nombre' => $g->name,
            'monto_objetivo' => $this->money($g->target_amount),
            'fecha_objetivo' => $this->date($g->target_date),
            'prioridad' => $g->priority?->value ?? '',
            'aporte_mensual' => $g->monthly_commitment !== null ? $this->money($g->monthly_commitment) : '',
            'fondo_emergencia' => $g->is_emergency_fund ? 'sí' : 'no',
            'estado' => $g->status?->value ?? '',
            'notas' => $g->notes ?? '',
        ])->toArray();

        $contributionRows = $contributions->map(fn ($c) => [
            'id' => $c->id,
            'meta_id' => $c->savings_goal_id,
            'fecha' => $this->date($c->date),
            'monto' => $this->money($c->amount),
            'tipo' => $c->type?->value ?? '',
            'notas' => $c->notes ?? '',
        ])->toArray();

        $reminderRows = $reminders->map(fn ($r) => [
            'id' => $r->id,
            'titulo' => $r->title,
            'monto' => $r->amount !== null ? $this->money($r->amount) : '',
            'fecha_vencimiento' => $this->date($r->due_date),
            'frecuencia' => $r->frequency?->value ?? '',
            'estado' => $r->status?->value ?? '',
            'notas' => $r->notes ?? '',
        ])->toArray();

        $userRow = [
            'nombre' => $user->name,
            'correo' => $user->email,
            'correo_verificado' => $user->email_verified_at ? $this->date($user->email_verified_at) : 'no',
            'fecha_nacimiento' => $this->date($user->birth_date),
            'region' => $user->region ?? '',
            'genero' => $user->gender ?? '',
            'miembro_desde' => $this->date($user->created_at),
        ];

        $csv = [
            'usuario.csv' => $this->toCsv([$userRow]),
            'cuentas.csv' => $this->toCsv($accountRows),
            'ingresos.csv' => $this->toCsv($incomeRows),
            'gastos.csv' => $this->toCsv($expenseRows),
            'presupuestos.csv' => $this->toCsv($budgetRows),
            'gastos-recurrentes.csv' => $this->toCsv($recurringRows),
            'deudas.csv' => $this->toCsv($debtRows),
            'pagos-deuda.csv' => $this->toCsv($paymentRows),
            'refinanciaciones.csv' => $this->toCsv($refinancingRows),
            'metas-ahorro.csv' => $this->toCsv($goalRows),
            'aportes-metas.csv' => $this->toCsv($contributionRows),
            'recordatorios.csv' => $this->toCsv($reminderRows),
        ];

        $json = [
            'exportado_en' => now()->toIso8601String(),
            'hogar' => ['id' => $household->id, 'nombre' => $household->name],
            'usuario' => $userRow,
            'cuentas' => $accountRows,
            'ingresos' => $incomeRows,
            'gastos' => $expenseRows,
            'presupuestos' => $budgetRows,
            'gastos_recurrentes' => $recurringRows,
            'deudas' => $debtRows,
            'pagos_deuda' => $paymentRows,
            'refinanciaciones' => $refinancingRows,
            'metas_ahorro' => $goalRows,
            'aportes_metas' => $contributionRows,
            'recordatorios' => $reminderRows,
        ];

        return ['csv' => $csv, 'json' => $json];
    }

    /**
     * Convierte un array de filas en CSV con BOM UTF-8 y separador `;`.
     * El BOM garantiza que Excel (Colombia) abra el archivo sin configurar
     * nada. El separador `;` evita conflicto con la coma en decimales.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function toCsv(array $rows): string
    {
        if (empty($rows)) {
            return "\xEF\xBB\xBF";
        }

        $bom = "\xEF\xBB\xBF";
        $output = fopen('php://temp', 'r+');
        $headers = array_keys($rows[0]);

        fputcsv($output, $headers, ';', '"', '\\');

        foreach ($rows as $row) {
            fputcsv($output, array_values($row), ';', '"', '\\');
        }

        rewind($output);
        $content = stream_get_contents($output);
        fclose($output);

        return $bom.$content;
    }

    private function money(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        return number_format((float) $value, 2, ',', '');
    }

    private function decimal(float $value): string
    {
        return number_format($value, 3, ',', '');
    }

    private function date(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format(self::DATE_FORMAT);
        }

        return '';
    }

    private function readme(Household $household): string
    {
        $now = now()->format('d/m/Y H:i');

        return <<<TXT
            FINLIA — Exportación de datos
            Hogar: {$household->name}
            Generado: {$now}

            ===== ARCHIVOS =====

            usuario.csv         — Perfil del usuario que solicitó la exportación.
                                  (Solo sus datos: nunca contraseña ni datos de otros miembros.)
            cuentas.csv         — Cuentas bancarias, de efectivo y tarjetas del hogar.
            ingresos.csv        — Movimientos de entrada registrados.
            gastos.csv          — Movimientos de salida registrados.
            presupuestos.csv    — Presupuestos por mes y categoría.
            gastos-recurrentes.csv — Obligaciones y suscripciones periódicas.
            deudas.csv          — Préstamos, tarjetas y otras deudas.
            pagos-deuda.csv     — Historial de pagos registrados contra cada deuda.
            refinanciaciones.csv — Cambios de condiciones en deudas.
            metas-ahorro.csv    — Objetivos de ahorro.
            aportes-metas.csv   — Depósitos y retiros de cada meta.
            recordatorios.csv   — Recordatorios del hogar.
            finlia.json         — Todos los datos anteriores en formato JSON
                                  (útil para migración a otra herramienta).
            README.txt          — Este archivo.

            ===== FORMATO =====

            Fechas:   DD/MM/AAAA
            Montos:   número con coma decimal  (ej. 1500000,00 = $ 1 500 000)
            Textos:   UTF-8 con BOM (abre en Excel sin configuración adicional)
            Separador CSV: punto y coma (;)

            ===== IMPORTAR EN OTRA HERRAMIENTA =====

            Los CSV pueden abrirse en Excel, LibreOffice Calc o Google Sheets.
            En Excel: Datos → Desde texto/CSV → selecciona `;` como delimitador
            y `UTF-8` como codificación (el BOM lo hace automático en versiones
            recientes). Los IDs numéricos (cuenta_id, deuda_id, etc.) permiten
            relacionar las tablas entre sí como en una base de datos.

            TXT;
    }
}
