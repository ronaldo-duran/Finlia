@php
    $expense = $expense ?? null;
    $available = $available ?? null;
    $methods = collect(\App\Enums\PaymentMethod::cases())->mapWithKeys(fn ($m) => [$m->value => $m->label()]);
@endphp

{{-- 1. Valor: input real (validación nativa intacta) con tipografía grande.
     type="text" + data-money-input: type="number" no admite el punto de
     miles ("1.234.567"); resources/js/app.js formatea en vivo y reescribe
     a un numérico plano justo antes de enviar (ver FinliaMoney). --}}
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
        value="{{ old('amount', $expense?->amount) }}"
        placeholder="0"
        required
        autofocus
    >
    @error('amount')
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror
    <div class="form-text">Monto en COP. Usa la coma para decimales.</div>
    @if ($available !== null)
        <div class="small mt-1" data-remaining-hint data-available="{{ $available }}">
            Te quedarían <strong>@money($available)</strong> este mes.
        </div>
    @endif
</div>

{{-- 2. Categoría: chips de acceso rápido + selector completo. --}}
<div class="mb-3">
    <label class="form-label fw-semibold">Categoría</label>
    <div class="chip-row mb-2" data-category-chips>
        @foreach ($categories->take(4) as $category)
            <button type="button" class="chip {{ (string) old('category_id', $expense?->category_id) === (string) $category->id ? 'active' : '' }}"
                    data-category-value="{{ $category->id }}">
                {{ $category->name }}
            </button>
        @endforeach
    </div>
    <select id="category_id" name="category_id" class="form-select @error('category_id') is-invalid @enderror">
        <option value="" @selected(! old('category_id', $expense?->category_id))>Sin categoría</option>
        @foreach ($categories as $category)
            <option value="{{ $category->id }}" @selected((string) old('category_id', $expense?->category_id) === (string) $category->id)>{{ $category->name }}</option>
        @endforeach
    </select>
    @error('category_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
</div>

<div class="row g-3">
    {{-- 3. Cuenta / medio de pago --}}
    <div class="col-md-6">
        <x-form-select label="Cuenta / medio de pago" name="account_id" :options="$accounts"
                       valueKey="id" labelKey="name" :selected="$expense?->account_id"
                       placeholder="Selecciona una cuenta" required
                       smartSelect="expense_account" />
    </div>
    {{-- 4. Fecha --}}
    <div class="col-md-6">
        <x-form-input label="Fecha" name="date" type="date" :value="old('date', $expense?->date?->format('Y-m-d') ?? date('Y-m-d'))" required />
    </div>
</div>

{{-- 5. Descripción --}}
<x-form-input label="Descripción" name="description" :value="$expense?->description" placeholder="Ej: Mercado del mes" />

{{-- Medio de pago y notas: detrás de "Más detalles" para no saturar la pantalla. --}}
<details class="mb-3" @if(old('payment_method') || $expense?->payment_method || old('notes') || $expense?->notes) open @endif>
    <summary class="small fw-semibold text-finlia" style="cursor: pointer;">Más detalles</summary>
    <div class="mt-3">
        <x-form-select label="Medio de pago" name="payment_method" :options="$methods"
                       :selected="$expense?->payment_method?->value"
                       placeholder="(opcional)" />
        <div class="mb-3">
            <label for="notes" class="form-label fw-semibold">Notas <span class="text-muted small">(opcional)</span></label>
            <textarea name="notes" id="notes" rows="2" class="form-control @error('notes') is-invalid @enderror">{{ old('notes', $expense?->notes) }}</textarea>
            @error('notes')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
    </div>
</details>

@once
    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                // Chips de categoría: atajo visual que fija el <select> real.
                // Sincronizado en ambos sentidos: elegir un chip fija el select
                // (y solo ese chip queda iluminado); cambiar el select a mano
                // reilumina el chip que coincida, o ninguno si no es de los rápidos.
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

                // "Te quedarían $X este mes" tras restar el valor ingresado.
                document.querySelectorAll('[data-remaining-hint]').forEach(function (hint) {
                    var amount = hint.closest('form')?.querySelector('#amount');
                    if (!amount) return;
                    var available = parseFloat(hint.getAttribute('data-available')) || 0;
                    var strong = hint.querySelector('strong');
                    var formatter = new Intl.NumberFormat('es-CO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    amount.addEventListener('input', function () {
                        var value = parseFloat(window.FinliaMoney.parse(amount.value)) || 0;
                        strong.textContent = '$ ' + formatter.format(available - value);
                    });
                });
            });
        </script>
    @endpush
@endonce
