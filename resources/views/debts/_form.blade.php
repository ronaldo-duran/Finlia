{{--
    Alta y edición de una deuda, en forma de simulador (ADR-0023).

    El usuario declara lo que pacta con la entidad —monto, tasa y número de
    cuotas— y la aplicación calcula la cuota mensual y la fecha de fin, igual
    que un simulador de crédito. Así no puede registrar una deuda imposible,
    como 10.000.000 en 120 cuotas pagando 20.000 al mes.

    El dinero usa `data-money-input` (docs/UI_DESIGN.md), nunca type="number".
--}}
@php($debt = $debt ?? null)

{{-- Topes de cuotas por tipo. Van como JSON inerte (no se ejecuta) para que
     el límite lo siga mandando PHP y el JS no duplique la regla. --}}
<script type="application/json" data-debt-term-limits>@json(\App\Enums\DebtType::termLimits())</script>

<h2 class="h6 text-muted text-uppercase mb-2">1. Qué debes</h2>
<div class="row g-2 mb-4">
    <div class="col-12 col-sm-7">
        <label for="{{ $prefix }}name" class="form-label small fw-semibold">Nombre</label>
        <input type="text" name="name" id="{{ $prefix }}name" required maxlength="120"
               class="form-control @error('name') is-invalid @enderror"
               placeholder="Tarjeta Davivienda, Préstamo moto…"
               value="{{ old('name', $debt?->name) }}">
        @error('name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>

    <div class="col-12 col-sm-5">
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

    <div class="col-12">
        <label for="{{ $prefix }}institution" class="form-label small fw-semibold">
            Entidad <span class="text-muted fw-normal">(opcional)</span>
        </label>
        <input type="text" name="institution" id="{{ $prefix }}institution" maxlength="120"
               class="form-control @error('institution') is-invalid @enderror"
               placeholder="Bancolombia, Tía Marta…"
               value="{{ old('institution', $debt?->institution) }}">
        @error('institution')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
</div>

<h2 class="h6 text-muted text-uppercase mb-2">2. Lo que pactaste</h2>
<div class="row g-2">
    <div class="col-12">
        <label for="{{ $prefix }}original_amount" class="form-label small fw-semibold">Monto</label>
        <div class="input-group">
            <span class="input-group-text">$</span>
            <input type="text" inputmode="decimal" data-money-input data-sim-amount
                   name="original_amount" id="{{ $prefix }}original_amount" required
                   class="form-control @error('original_amount') is-invalid @enderror"
                   value="{{ old('original_amount', $debt?->original_amount) }}">
        </div>
        @error('original_amount')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>

    <div class="col-6">
        <label for="{{ $prefix }}interest_rate" class="form-label small fw-semibold">Tasa anual (E.A.)</label>
        <div class="input-group">
            <input type="number" name="interest_rate" id="{{ $prefix }}interest_rate"
                   step="0.001" min="0" max="999.999" data-sim-rate
                   class="form-control @error('interest_rate') is-invalid @enderror"
                   placeholder="0" value="{{ old('interest_rate', $debt?->interest_rate) }}">
            <span class="input-group-text">%</span>
        </div>
        <div class="form-text">Déjala en 0 si no te cobran intereses.</div>
        @error('interest_rate')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>

    <div class="col-6">
        <label for="{{ $prefix }}term_months" class="form-label small fw-semibold">N.º de cuotas</label>
        <input type="number" name="term_months" id="{{ $prefix }}term_months" min="1"
               max="{{ ($debt?->type ?? \App\Enums\DebtType::CreditCard)->maxTermMonths() }}"
               data-sim-term
               class="form-control @error('term_months') is-invalid @enderror"
               value="{{ old('term_months', $debt?->term_months) }}">
        <div class="form-text" data-term-help></div>
        @error('term_months')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>

    <div class="col-6">
        <label for="{{ $prefix }}start_date" class="form-label small fw-semibold">Fecha de inicio</label>
        <input type="date" name="start_date" id="{{ $prefix }}start_date"
               class="form-control @error('start_date') is-invalid @enderror"
               value="{{ old('start_date', $debt?->start_date?->format('Y-m-d')) }}">
        @error('start_date')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>

    <div class="col-6">
        <label for="{{ $prefix }}due_day" class="form-label small fw-semibold">Día de pago</label>
        <input type="number" name="due_day" id="{{ $prefix }}due_day" min="1" max="31"
               class="form-control @error('due_day') is-invalid @enderror"
               placeholder="15" value="{{ old('due_day', $debt?->due_day) }}">
        @error('due_day')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
</div>

{{-- Resultado del simulador: lo que sale de lo pactado. --}}
<div class="card bg-finlia-subtle border-0 mt-3 mb-4">
    <div class="card-body">
        <div class="row g-3 align-items-end">
            <div class="col-12 col-sm-6">
                <label for="{{ $prefix }}minimum_payment" class="form-label small fw-semibold mb-1">
                    Cuota mensual
                </label>
                <div class="input-group">
                    <span class="input-group-text">$</span>
                    <input type="text" inputmode="decimal" data-money-input data-sim-installment
                           name="minimum_payment" id="{{ $prefix }}minimum_payment" readonly
                           class="form-control @error('minimum_payment') is-invalid @enderror"
                           value="{{ old('minimum_payment', $debt?->minimum_payment) }}">
                </div>
                @error('minimum_payment')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>

            <div class="col-12 col-sm-6">
                <div class="small text-muted">Terminarías de pagar</div>
                <div class="fw-bold" data-sim-end>—</div>
                <div class="small text-muted mt-1" data-sim-interest></div>
            </div>

            <div class="col-12">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" value="1"
                           id="{{ $prefix }}adjust" data-sim-adjust
                           @checked(old('minimum_payment') !== null && $debt === null)>
                    <label class="form-check-label small" for="{{ $prefix }}adjust">
                        Mi entidad cobra otra cuota (seguros, cuota de manejo…)
                    </label>
                </div>
            </div>
        </div>
    </div>
</div>

<h2 class="h6 text-muted text-uppercase mb-2">3. Tu plan de pago <span class="fw-normal text-lowercase">(opcional)</span></h2>
<div class="row g-2 mb-4">
    <div class="col-12">
        <label for="{{ $prefix }}planned_payment" class="form-label small fw-semibold">Lo que planeas pagar al mes</label>
        <div class="input-group">
            <span class="input-group-text">$</span>
            <input type="text" inputmode="decimal" data-money-input data-sim-planned
                   name="planned_payment" id="{{ $prefix }}planned_payment"
                   class="form-control @error('planned_payment') is-invalid @enderror"
                   value="{{ old('planned_payment', $debt?->planned_payment) }}">
        </div>
        <div class="form-text" data-sim-plan-help>
            Déjalo vacío si vas a pagar la cuota. Si puedes abonar más, ponlo aquí y verás cuánto te ahorras.
        </div>
        @error('planned_payment')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
</div>

<h2 class="h6 text-muted text-uppercase mb-2">4. Detalles</h2>
<div class="row g-2">
    <div class="col-12">
        <label for="{{ $prefix }}account_id" class="form-label small fw-semibold">
            Cuenta asociada <span class="text-muted fw-normal">(opcional)</span>
        </label>
        <select name="account_id" id="{{ $prefix }}account_id" class="form-select">
            <option value="">Ninguna</option>
            @foreach ($accounts as $account)
                <option value="{{ $account->id }}" @selected((int) old('account_id', $debt?->account_id) === $account->id)>
                    {{ $account->name }}
                </option>
            @endforeach
        </select>
        <div class="form-text">Si la eliges, al registrar un pago se descuenta de esa cuenta.</div>
    </div>

    <div class="col-12 col-sm-6">
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

    @if ($debt !== null)
        <div class="col-12 col-sm-6">
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
        <label for="{{ $prefix }}notes" class="form-label small fw-semibold">
            Notas <span class="text-muted fw-normal">(opcional)</span>
        </label>
        <textarea name="notes" id="{{ $prefix }}notes" rows="2" maxlength="2000"
                  class="form-control @error('notes') is-invalid @enderror">{{ old('notes', $debt?->notes) }}</textarea>
        @error('notes')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
</div>
