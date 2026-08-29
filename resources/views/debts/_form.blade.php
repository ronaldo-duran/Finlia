{{--
    Campos de una deuda. Compartido por el alta (panel) y la edición (detalle).
    $debt es null al crear.
--}}
@php($debt = $debt ?? null)

<div class="row g-2">
    <div class="col-12">
        <label for="{{ $prefix }}name" class="form-label small fw-semibold">Nombre</label>
        <input type="text" name="name" id="{{ $prefix }}name" required maxlength="120"
               class="form-control @error('name') is-invalid @enderror"
               placeholder="Tarjeta Davivienda, Préstamo moto…"
               value="{{ old('name', $debt?->name) }}">
        @error('name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>

    <div class="col-12 col-sm-6">
        <label for="{{ $prefix }}type" class="form-label small fw-semibold">Tipo</label>
        <select name="type" id="{{ $prefix }}type" required data-debt-type
                class="form-select @error('type') is-invalid @enderror">
            @foreach (\App\Enums\DebtType::cases() as $case)
                <option value="{{ $case->value }}" @selected(old('type', $debt?->type?->value) === $case->value)>
                    {{ $case->label() }}
                </option>
            @endforeach
        </select>
        @error('type')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>

    <div class="col-12 col-sm-6">
        <label for="{{ $prefix }}institution" class="form-label small fw-semibold">Entidad <span class="text-muted fw-normal">(opcional)</span></label>
        <input type="text" name="institution" id="{{ $prefix }}institution" maxlength="120"
               class="form-control @error('institution') is-invalid @enderror"
               placeholder="Bancolombia, Tía Marta…"
               value="{{ old('institution', $debt?->institution) }}">
        @error('institution')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>

    <div class="col-12">
        <label for="{{ $prefix }}original_amount" class="form-label small fw-semibold">Monto original</label>
        <div class="input-group">
            <span class="input-group-text">$</span>
            <input type="number" name="original_amount" id="{{ $prefix }}original_amount" required
                   step="0.01" min="0.01"
                   class="form-control @error('original_amount') is-invalid @enderror"
                   value="{{ old('original_amount', $debt?->original_amount) }}">
        </div>
        <div class="form-text">El saldo actual se calcula solo, a partir de los pagos que registres.</div>
        @error('original_amount')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>

    <div class="col-7 col-sm-6">
        <label for="{{ $prefix }}interest_rate" class="form-label small fw-semibold">Tasa anual <span class="text-muted fw-normal">(%)</span></label>
        <input type="number" name="interest_rate" id="{{ $prefix }}interest_rate" step="0.001" min="0" max="999.999"
               class="form-control @error('interest_rate') is-invalid @enderror"
               placeholder="28.5"
               value="{{ old('interest_rate', $debt?->interest_rate) }}">
        @error('interest_rate')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>

    <div class="col-5 col-sm-6">
        <label for="{{ $prefix }}interest_rate_type" class="form-label small fw-semibold">Tipo de tasa</label>
        <select name="interest_rate_type" id="{{ $prefix }}interest_rate_type" class="form-select">
            <option value="">—</option>
            @foreach (\App\Enums\InterestRateType::cases() as $case)
                <option value="{{ $case->value }}" @selected(old('interest_rate_type', $debt?->interest_rate_type?->value) === $case->value)>
                    {{ $case->label() }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="col-12 col-sm-6">
        <label for="{{ $prefix }}minimum_payment" class="form-label small fw-semibold">Cuota mínima</label>
        <div class="input-group">
            <span class="input-group-text">$</span>
            <input type="number" name="minimum_payment" id="{{ $prefix }}minimum_payment" step="0.01" min="0"
                   class="form-control @error('minimum_payment') is-invalid @enderror"
                   value="{{ old('minimum_payment', $debt?->minimum_payment) }}">
        </div>
        <div class="form-text">Lo que te exige la entidad cada mes.</div>
        @error('minimum_payment')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>

    <div class="col-12 col-sm-6">
        <label for="{{ $prefix }}planned_payment" class="form-label small fw-semibold">Lo que planeas pagar</label>
        <div class="input-group">
            <span class="input-group-text">$</span>
            <input type="number" name="planned_payment" id="{{ $prefix }}planned_payment" step="0.01" min="0"
                   class="form-control @error('planned_payment') is-invalid @enderror"
                   value="{{ old('planned_payment', $debt?->planned_payment) }}">
        </div>
        <div class="form-text">
            Si solo vas a pagar el mínimo, déjalo vacío. Si abonas de más,
            ponlo aquí: es lo que se usa para saber cuándo terminarías.
        </div>
        @error('planned_payment')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>

    <div class="col-4">
        <label for="{{ $prefix }}due_day" class="form-label small fw-semibold">Día de pago</label>
        <input type="number" name="due_day" id="{{ $prefix }}due_day" min="1" max="31"
               class="form-control @error('due_day') is-invalid @enderror"
               value="{{ old('due_day', $debt?->due_day) }}">
        @error('due_day')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>

    <div class="col-4">
        <label for="{{ $prefix }}start_date" class="form-label small fw-semibold">Inicio</label>
        <input type="date" name="start_date" id="{{ $prefix }}start_date"
               class="form-control @error('start_date') is-invalid @enderror"
               value="{{ old('start_date', $debt?->start_date?->format('Y-m-d')) }}">
        @error('start_date')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>

    <div class="col-4">
        <label for="{{ $prefix }}term_months" class="form-label small fw-semibold">N.º de cuotas</label>
        <input type="number" name="term_months" id="{{ $prefix }}term_months" min="1"
               max="{{ $debt?->type?->maxTermMonths() ?? \App\Enums\DebtType::CreditCard->maxTermMonths() }}"
               data-term-input
               class="form-control @error('term_months') is-invalid @enderror"
               value="{{ old('term_months', $debt?->term_months) }}">
        @error('term_months')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>

    <div class="col-12">
        <div class="form-text" data-term-help>
            El fin previsto se calcula solo: inicio + cuotas.
        </div>
    </div>

    <div class="col-12">
        <label for="{{ $prefix }}account_id" class="form-label small fw-semibold">Cuenta asociada <span class="text-muted fw-normal">(opcional)</span></label>
        <select name="account_id" id="{{ $prefix }}account_id" class="form-select">
            <option value="">Ninguna</option>
            @foreach ($accounts as $account)
                <option value="{{ $account->id }}" @selected((int) old('account_id', $debt?->account_id) === $account->id)>
                    {{ $account->name }}
                </option>
            @endforeach
        </select>
    </div>

    @if ($debt !== null)
        <div class="col-12">
            <label for="{{ $prefix }}status" class="form-label small fw-semibold">Estado</label>
            <select name="status" id="{{ $prefix }}status" class="form-select">
                @foreach (\App\Enums\DebtStatus::cases() as $case)
                    <option value="{{ $case->value }}" @selected(old('status', $debt->status->value) === $case->value)>
                        {{ $case->label() }}
                    </option>
                @endforeach
            </select>
        </div>
    @endif

    <div class="col-12">
        <label for="{{ $prefix }}notes" class="form-label small fw-semibold">Notas <span class="text-muted fw-normal">(opcional)</span></label>
        <textarea name="notes" id="{{ $prefix }}notes" rows="2" maxlength="2000"
                  class="form-control @error('notes') is-invalid @enderror">{{ old('notes', $debt?->notes) }}</textarea>
        @error('notes')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
</div>


@once
    @push('scripts')
        <script>
            // Tope de cuotas por tipo de deuda. La validación de verdad está
            // en el servidor (StoreDebtRequest); esto solo evita que el
            // usuario escriba un número que le van a rechazar.
            (function () {
                const limites = @js(\App\Enums\DebtType::termLimits());

                document.querySelectorAll('[data-debt-type]').forEach(function (select) {
                    const form = select.closest('form');
                    if (!form) return;

                    const input = form.querySelector('[data-term-input]');
                    const ayuda = form.querySelector('[data-term-help]');
                    if (!input) return;

                    function ajustar() {
                        const max = limites[select.value];
                        if (!max) return;

                        input.max = max;
                        if (input.value && Number(input.value) > max) {
                            input.value = max;
                        }
                        if (ayuda) {
                            ayuda.textContent =
                                'El fin previsto se calcula solo: inicio + cuotas. Máximo ' + max + ' cuotas para este tipo.';
                        }
                    }

                    select.addEventListener('change', ajustar);
                    ajustar();
                });
            })();
        </script>
    @endpush
@endonce
