@php
    $account = $account ?? null;
    $types = collect(\App\Enums\AccountType::cases())->mapWithKeys(fn ($t) => [$t->value => $t->label()]);
    $currencies = ['COP' => 'COP (Peso colombiano)', 'USD' => 'USD', 'EUR' => 'EUR'];
@endphp

<x-form-input label="Nombre" name="name" :value="$account?->name" required autofocus placeholder="Ej: Davivienda ahorros" />

<div class="row g-3">
    <div class="col-md-6">
        <x-form-select label="Tipo" name="type" :options="$types" :selected="$account?->type?->value ?? 'cash'" required />
    </div>
    <div class="col-md-6">
        <x-form-select label="Moneda" name="currency" :options="$currencies" :selected="$account?->currency ?? 'COP'" required />
    </div>
</div>

<div class="row g-3">
    <div class="col-md-6">
        <x-form-input label="Saldo inicial" name="initial_balance" type="number" :value="$account?->initial_balance" required help="Saldo al crear la cuenta. El saldo actual se calcula desde los movimientos." />
    </div>
    <div class="col-md-6 d-flex align-items-end">
        <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" role="switch" name="is_active" value="1" id="is_active"
                   @checked((bool) old('is_active', $account?->is_active ?? true))>
            <label class="form-check-label fw-semibold" for="is_active">Cuenta activa</label>
        </div>
    </div>
</div>

<div class="mb-3">
    <label for="notes" class="form-label fw-semibold">Notas <span class="text-muted small">(opcional)</span></label>
    <textarea name="notes" id="notes" rows="2" class="form-control @error('notes') is-invalid @enderror"
              placeholder="Ej: tarjeta de la clase de inglés">{{ old('notes', $account?->notes) }}</textarea>
    @error('notes')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
</div>
