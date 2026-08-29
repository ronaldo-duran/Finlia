<?php

declare(strict_types=1);

namespace App\Http\Requests\Debt;

use App\Enums\DebtStatus;
use App\Enums\DebtType;
use App\Enums\InterestRateType;
use App\Services\DebtCalculator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Valida el alta de una deuda.
 *
 * No acepta household_id (sale del hogar activo) ni current_balance (es
 * derivado, ADR-0020). account_id se acota a cuentas del hogar activo
 * (aislamiento, ADR-0005).
 */
class StoreDebtRequest extends FormRequest
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
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'institution' => ['nullable', 'string', 'max:120'],
            'type' => ['required', Rule::enum(DebtType::class)],
            'original_amount' => ['required', 'numeric', 'min:0.01', 'max:9999999999999.99'],
            // % efectivo anual. 0 es válido (préstamo familiar sin intereses).
            'interest_rate' => ['nullable', 'numeric', 'min:0', 'max:999.999'],
            'interest_rate_type' => ['nullable', Rule::enum(InterestRateType::class)],
            // Cuota mínima: lo que EXIGE la entidad.
            'minimum_payment' => ['nullable', 'numeric', 'min:0', 'max:9999999999999.99'],
            // Lo que el usuario PLANEA pagar: nunca por debajo del mínimo, o
            // el plan sería incumplir (ADR-0022). La comparación solo aplica
            // si hay mínimo declarado; si no, `gte` compararía contra null y
            // rechazaría cualquier valor.
            'planned_payment' => array_filter([
                'nullable', 'numeric', 'min:0', 'max:9999999999999.99',
                $this->filled('minimum_payment') ? 'gte:minimum_payment' : null,
            ]),
            // Número de cuotas, con tope según el tipo de deuda.
            'term_months' => ['nullable', 'integer', 'min:1', 'max:'.$this->maxTermMonths()],
            'due_day' => ['nullable', 'integer', 'between:1,31'],
            'start_date' => ['nullable', 'date', 'before:2100-01-01'],
            'status' => ['nullable', Rule::enum(DebtStatus::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
            'account_id' => [
                'nullable',
                Rule::exists('accounts', 'id')->where(fn ($q) => $q->where('household_id', active_household_id())),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Ponle un nombre a la deuda.',
            'type.required' => 'Selecciona el tipo de deuda.',
            'original_amount.required' => 'Indica el monto original de la deuda.',
            'original_amount.min' => 'El monto original debe ser mayor que cero.',
            'due_day.between' => 'El día de pago debe estar entre 1 y 31.',
            'interest_rate.max' => 'Revisa la tasa: parece demasiado alta.',
            'planned_payment.gte' => 'Lo que planeas pagar no puede ser menor que la cuota mínima.',
            'term_months.min' => 'El número de cuotas debe ser al menos 1.',
            'term_months.max' => 'Para este tipo de deuda el máximo son :max cuotas.',
        ];
    }

    /**
     * Tope de cuotas del tipo seleccionado. Si el tipo no es válido, se usa el
     * mayor de todos: la regla de `type` ya rechazará la petición y así no se
     * enmascara ese error con uno de plazo.
     */
    private function maxTermMonths(): int
    {
        // `type[]=x` haría que input() devuelva un array: castearlo a string
        // emite un warning que Laravel convierte en 500 (mismo fallo que ya
        // se corrigió en el panel de deudas).
        $requested = $this->input('type');
        $type = is_string($requested) ? DebtType::tryFrom($requested) : null;

        return $type?->maxTermMonths() ?? max(DebtType::termLimits());
    }

    /**
     * Coherencia entre lo que el usuario declara y lo que va a pasar de verdad
     * (ADR-0023). Sin esto se puede registrar una deuda imposible: 10.000.000
     * a 120 cuotas pagando 20.000 al mes, que en realidad tardaría 500 meses.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $datos = $validator->validated();

            $monto = isset($datos['original_amount']) ? (float) $datos['original_amount'] : null;
            $tasa = isset($datos['interest_rate']) ? (float) $datos['interest_rate'] : null;
            $cuotas = isset($datos['term_months']) ? (int) $datos['term_months'] : null;
            $minima = isset($datos['minimum_payment']) ? (float) $datos['minimum_payment'] : null;

            $calc = app(DebtCalculator::class);

            // 1. La cuota tiene que cubrir al menos los intereses, o el saldo
            //    sube en vez de bajar y la deuda no se acaba nunca.
            $interesMensual = $calc->firstMonthInterest($monto, $tasa);

            if ($minima !== null && $minima > 0.0 && $interesMensual > 0.0 && $minima <= $interesMensual) {
                $validator->errors()->add('minimum_payment', __(
                    'Con esa cuota no cubres ni los intereses del primer mes (:interes). La deuda nunca bajaría.',
                    ['interes' => money($interesMensual)],
                ));

                return;
            }

            // 2. La cuota tiene que bastar para saldar el monto en el plazo
            //    pactado. Se compara contra la cuota teórica con una holgura
            //    del 1 % para no pelear por céntimos de redondeo.
            if ($monto === null || $cuotas === null || $minima === null || $minima <= 0.0) {
                return;
            }

            $requerida = $calc->installment($monto, $tasa, $cuotas);

            if ($requerida !== null && $minima < $requerida * 0.99) {
                $validator->errors()->add('minimum_payment', __(
                    'Con :cuota al mes, :monto no se pagan en :cuotas cuotas: harían falta :requerida. Ajusta la cuota o el número de cuotas.',
                    [
                        'cuota' => money($minima),
                        'monto' => money($monto),
                        'cuotas' => $cuotas,
                        'requerida' => money($requerida),
                    ],
                ));
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function validatedData(): array
    {
        $data = array_map(
            static fn ($value) => is_string($value) ? trim($value) : $value,
            $this->validated(),
        );

        $data['status'] ??= DebtStatus::Active->value;

        return $data;
    }
}
