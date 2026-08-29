<?php

declare(strict_types=1);

namespace App\Http\Requests\SavingsGoal;

use App\Enums\SavingsGoalContributionType;
use App\Models\SavingsGoal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Valida el registro de un aporte o retiro sobre una meta de ahorro.
 *
 * Los movimientos no mueven cuentas (ADR-0025), así que no hay account_id:
 * solo monto, tipo, fecha y nota. El monto va siempre positivo; la
 * dirección la da el tipo.
 */
class StoreSavingsGoalContributionRequest extends FormRequest
{
    /**
     * Autoriza ANTES de validar (aislamiento multi-hogar, ADR-0005, mismo
     * patrón que UpdateExpenseRequest): las reglas dependientes de la meta
     * (withValidator) incrustan su estado y saldo en los mensajes de error,
     * así que un usuario ajeno debe recibir 403 sin llegar a validar.
     */
    public function authorize(): bool
    {
        $goal = $this->route('savingsGoal');

        return $goal instanceof SavingsGoal
            && ($this->user()?->can('contribute', $goal) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01', 'max:9999999999999.99'],
            'date' => ['required', 'date', 'before_or_equal:today'],
            'type' => ['required', Rule::enum(SavingsGoalContributionType::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.required' => 'Indica cuánto vas a mover.',
            'amount.min' => 'El monto debe ser mayor que cero.',
            'date.required' => 'Indica la fecha del movimiento.',
            'date.before_or_equal' => 'No se pueden registrar movimientos con fecha futura.',
            'type.required' => 'Indica si es un aporte o un retiro.',
        ];
    }

    /**
     * Reglas que dependen de la meta: no se puede retirar más de lo ahorrado,
     * ni mover una meta lograda o archivada.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var SavingsGoal|null $goal */
            $goal = $this->route('savingsGoal');

            if ($goal === null) {
                return;
            }

            if (! $goal->status->acceptsContributions()) {
                $validator->errors()->add('type', __(
                    'La meta está :estado: no acepta más movimientos.',
                    ['estado' => $goal->status->label()],
                ));

                return;
            }

            $datos = $validator->validated();
            $monto = isset($datos['amount']) ? (float) $datos['amount'] : 0.0;

            if (($datos['type'] ?? null) === SavingsGoalContributionType::Withdrawal->value
                && $monto > (float) $goal->current_amount) {
                $validator->errors()->add('amount', __(
                    'Solo hay :disponible ahorrado en la meta: no puedes retirar más.',
                    ['disponible' => money($goal->current_amount)],
                ));
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function validatedData(): array
    {
        return array_map(
            static fn ($value) => is_string($value) ? trim($value) : $value,
            $this->validated(),
        );
    }
}
