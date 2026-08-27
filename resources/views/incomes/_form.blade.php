@php $income = $income ?? null; @endphp

{{-- 1. Valor: input real (validación nativa intacta) con tipografía grande. --}}
<div class="mb-3 text-center">
    <label for="amount" class="form-label fw-semibold text-uppercase small text-muted">Valor</label>
    <input
        id="amount"
        type="number"
        name="amount"
        inputmode="decimal"
        step="0.01"
        min="0"
        class="form-control border-0 bg-transparent text-center fw-bold mx-auto @error('amount') is-invalid @enderror"
        style="font-size: clamp(1.75rem, 8vw, 2.5rem); max-width: 320px; box-shadow: none;"
        value="{{ old('amount', $income?->amount) }}"
        placeholder="0"
        required
        autofocus
    >
    @error('amount')
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror
    <div class="form-text">Monto en COP. Usa la coma para decimales.</div>
</div>

{{-- 2. Categoría: chips de acceso rápido + selector completo. --}}
<div class="mb-3">
    <label class="form-label fw-semibold">Categoría</label>
    <div class="chip-row mb-2" data-category-chips>
        @foreach ($categories->take(4) as $category)
            <button type="button" class="chip {{ (string) old('category_id', $income?->category_id) === (string) $category->id ? 'active' : '' }}"
                    data-category-value="{{ $category->id }}">
                {{ $category->name }}
            </button>
        @endforeach
    </div>
    <select id="category_id" name="category_id" class="form-select @error('category_id') is-invalid @enderror">
        <option value="" @selected(! old('category_id', $income?->category_id))>Sin categoría</option>
        @foreach ($categories as $category)
            <option value="{{ $category->id }}" @selected((string) old('category_id', $income?->category_id) === (string) $category->id)>{{ $category->name }}</option>
        @endforeach
    </select>
    @error('category_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
</div>

<div class="row g-3">
    {{-- 3. Cuenta --}}
    <div class="col-md-6">
        <x-form-select label="Cuenta" name="account_id" :options="$accounts"
                       valueKey="id" labelKey="name" :selected="$income?->account_id"
                       placeholder="Selecciona una cuenta" required />
    </div>
    {{-- 4. Fecha --}}
    <div class="col-md-6">
        <x-form-input label="Fecha" name="date" type="date" :value="old('date', $income?->date?->format('Y-m-d') ?? date('Y-m-d'))" required />
    </div>
</div>

{{-- 5. Descripción --}}
<x-form-input label="Descripción" name="description" :value="$income?->description" placeholder="Ej: Pago quincenal" />

<details class="mb-3" @if(old('source') || $income?->source || old('notes') || $income?->notes) open @endif>
    <summary class="small fw-semibold text-finlia" style="cursor: pointer;">Más detalles</summary>
    <div class="mt-3">
        <x-form-input label="Origen" name="source" :value="$income?->source" placeholder="Ej: Salario, Freelance" />
        <div class="mb-3">
            <label for="notes" class="form-label fw-semibold">Notas <span class="text-muted small">(opcional)</span></label>
            <textarea name="notes" id="notes" rows="2" class="form-control @error('notes') is-invalid @enderror">{{ old('notes', $income?->notes) }}</textarea>
            @error('notes')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
    </div>
</details>

@once
    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                document.querySelectorAll('[data-category-chips]').forEach(function (row) {
                    var select = row.closest('form')?.querySelector('#category_id');
                    if (!select) return;
                    row.querySelectorAll('[data-category-value]').forEach(function (chip) {
                        chip.addEventListener('click', function () {
                            select.value = chip.getAttribute('data-category-value');
                            row.querySelectorAll('.chip').forEach(function (c) { c.classList.remove('active'); });
                            chip.classList.add('active');
                        });
                    });
                });
            });
        </script>
    @endpush
@endonce
