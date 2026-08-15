@php
    $expense = $expense ?? null;
    $methods = collect(\App\Enums\PaymentMethod::cases())->mapWithKeys(fn ($m) => [$m->value => $m->label()]);
@endphp

{{-- 1. Valor --}}
<x-form-input label="Valor" name="amount" type="number" :value="$expense?->amount" required autofocus
              placeholder="0" help="Monto en COP. Usa la coma para decimales." />

<div class="row g-3">
    {{-- 2. Categoría --}}
    <div class="col-md-6">
        <x-form-select label="Categoría" name="category_id" :options="$categories"
                       valueKey="id" labelKey="name" :selected="$expense?->category_id"
                       placeholder="Sin categoría" />
    </div>
    {{-- 3. Cuenta / medio de pago --}}
    <div class="col-md-6">
        <x-form-select label="Cuenta / medio de pago" name="account_id" :options="$accounts"
                       valueKey="id" labelKey="name" :selected="$expense?->account_id"
                       placeholder="Selecciona una cuenta" required />
    </div>
</div>

<div class="row g-3">
    {{-- 4. Fecha --}}
    <div class="col-md-6">
        <x-form-input label="Fecha" name="date" type="date" :value="old('date', $expense?->date?->format('Y-m-d') ?? date('Y-m-d'))" required />
    </div>
    <div class="col-md-6">
        <x-form-select label="Medio de pago" name="payment_method" :options="$methods"
                       :selected="$expense?->payment_method?->value"
                       placeholder="(opcional)" />
    </div>
</div>

{{-- 5. Descripción --}}
<x-form-input label="Descripción" name="description" :value="$expense?->description" placeholder="Ej: Mercado del mes" />

<div class="mb-3">
    <label for="notes" class="form-label fw-semibold">Notas <span class="text-muted small">(opcional)</span></label>
    <textarea name="notes" id="notes" rows="2" class="form-control @error('notes') is-invalid @enderror">{{ old('notes', $expense?->notes) }}</textarea>
    @error('notes')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
</div>
