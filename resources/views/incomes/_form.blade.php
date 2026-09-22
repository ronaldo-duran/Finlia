@php
    $income = $income ?? null;
    /**
     * Prellenado desde query params (p. ej. el botón «Registrar ingreso» del
     * aviso de ingreso previsto pendiente): solo aplica al alta, y no
     * sobrescribe old() ni los valores del modelo si estamos editando.
     * request()->query() vive fuera del Service (ADR-0010: no hay HTTP en
     * los servicios); aquí es Blade, así que es legítimo.
     */
    $prefill = $income === null ? request()->query() : [];
@endphp

<div class="mb-3 text-center">
    <label for="amount" class="form-label fw-semibold text-uppercase small text-muted">Valor</label>
    <input
        id="amount"
        type="text"
        name="amount"
        inputmode="decimal"
        data-money-input
        class="form-control border-0 bg-transparent text-center fw-bold mx-auto @error('amount') is-invalid @enderror"
        style="font-size: clamp(1.75rem, 8vw, 2.5rem); max-width: 320px; box-shadow: none;"
        value="{{ old('amount', $income?->amount ?? ($prefill['amount'] ?? null)) }}"
        placeholder="0"
        required
        autofocus
    >
    @error('amount')
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror
    <div class="form-text">Monto en COP. Usa la coma para decimales.</div>
</div>
<div class="mb-3">
    <label class="form-label fw-semibold">Categoría</label>
    @php $selectedCategoryId = old('category_id', $income?->category_id ?? ($prefill['category_id'] ?? null)); @endphp
    <div class="chip-row mb-2" data-category-chips>
        @foreach ($categories->take(4) as $category)
            <button type="button" class="chip {{ (string) $selectedCategoryId === (string) $category->id ? 'active' : '' }}"
                    data-category-value="{{ $category->id }}">
                {{ $category->name }}
            </button>
        @endforeach
    </div>
    <select id="category_id" name="category_id" class="form-select @error('category_id') is-invalid @enderror">
        <option value="" @selected(! $selectedCategoryId)>Sin categoría</option>
        @foreach ($categories as $category)
            <option value="{{ $category->id }}" @selected((string) $selectedCategoryId === (string) $category->id)>{{ $category->name }}</option>
        @endforeach
    </select>
    @error('category_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
</div>
<div class="row g-3">
    <div class="col-md-6">
        <x-form-select label="Cuenta" name="account_id" :options="$accounts"
                       valueKey="id" labelKey="name" :selected="$income?->account_id"
                       placeholder="Selecciona una cuenta" required
                       smartSelect="income_account" />
    </div>
    <div class="col-md-6">
        <x-form-input label="Fecha" name="date" type="date" :value="old('date', $income?->date?->format('Y-m-d') ?? ($prefill['date'] ?? date('Y-m-d')))" required />
    </div>
</div>
<x-form-input label="Descripción" name="description" :value="$income?->description ?? ($prefill['description'] ?? null)" placeholder="Ej: Pago quincenal" />

<details class="mb-3" @if(old('source') || $income?->source || old('notes') || $income?->notes || ! empty($prefill['source'])) open @endif>
    <summary class="small fw-semibold text-finlia" style="cursor: pointer;">Más detalles</summary>
    <div class="mt-3">
        <x-form-input label="Origen" name="source" :value="$income?->source ?? ($prefill['source'] ?? null)" placeholder="Ej: Salario, Freelance" />
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
                    function syncChips(value) {
                        row.querySelectorAll('.chip').forEach(function (c) {
                            c.classList.toggle('active', c.getAttribute('data-category-value') === value);
                        });
                    }
                    row.querySelectorAll('[data-category-value]').forEach(function (chip) {
                        chip.addEventListener('click', function () {
                            select.value = chip.getAttribute('data-category-value');
                            syncChips(select.value);
                        });
                    });
                    select.addEventListener('change', function () { syncChips(select.value); });
                });
            });
        </script>
    @endpush
@endonce
