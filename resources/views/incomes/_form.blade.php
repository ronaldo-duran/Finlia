@php $income = $income ?? null; @endphp

{{-- 1. Valor --}}
<x-form-input label="Valor" name="amount" type="number" :value="$income?->amount" required autofocus
              placeholder="0" help="Monto en COP. Usa la coma para decimales." />

<div class="row g-3">
    {{-- 2. Categoría --}}
    <div class="col-md-6">
        <x-form-select label="Categoría" name="category_id" :options="$categories"
                       valueKey="id" labelKey="name" :selected="$income?->category_id"
                       placeholder="Sin categoría" />
    </div>
    {{-- 3. Cuenta --}}
    <div class="col-md-6">
        <x-form-select label="Cuenta" name="account_id" :options="$accounts"
                       valueKey="id" labelKey="name" :selected="$income?->account_id"
                       placeholder="Selecciona una cuenta" required />
    </div>
</div>

<div class="row g-3">
    {{-- 4. Fecha --}}
    <div class="col-md-6">
        <x-form-input label="Fecha" name="date" type="date" :value="old('date', $income?->date?->format('Y-m-d') ?? date('Y-m-d'))" required />
    </div>
    <div class="col-md-6">
        <x-form-input label="Origen" name="source" :value="$income?->source" placeholder="Ej: Salario, Freelance" />
    </div>
</div>

{{-- 5. Descripción --}}
<x-form-input label="Descripción" name="description" :value="$income?->description" placeholder="Ej: Pago quincenal" />

<div class="mb-3">
    <label for="notes" class="form-label fw-semibold">Notas <span class="text-muted small">(opcional)</span></label>
    <textarea name="notes" id="notes" rows="2" class="form-control @error('notes') is-invalid @enderror">{{ old('notes', $income?->notes) }}</textarea>
    @error('notes')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
</div>
