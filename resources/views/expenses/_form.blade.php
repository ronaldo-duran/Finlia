@php
    $expense = $expense ?? null;
    $available = $available ?? null;
    $debtsCategoryId = $debtsCategoryId ?? null;
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
            Te quedarían <strong>@money($available)</strong>
            @isset($availableUntil) hasta el {{ $availableUntil->format('d/m/Y') }}. @else este mes. @endisset
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
    <select id="category_id" name="category_id" class="form-select @error('category_id') is-invalid @enderror"
            @if ($debtsCategoryId) data-debts-category-id="{{ $debtsCategoryId }}" @endif>
        <option value="" @selected(! old('category_id', $expense?->category_id))>Sin categoría</option>
        @foreach ($categories as $category)
            <option value="{{ $category->id }}" @selected((string) old('category_id', $expense?->category_id) === (string) $category->id)>{{ $category->name }}</option>
        @endforeach
    </select>
    @error('category_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror

    @if ($debtsCategoryId)
        <div class="alert alert-info small mt-2 d-none" data-debts-hint role="note">
            <i class="bi bi-info-circle me-1"></i>
            ¿Vas a pagar una deuda que ya registraste?
            <a href="{{ route('debts.index') }}" class="alert-link">
                Regístralo desde el flujo de pago de deuda
            </a>
            para que descuente el saldo. Si es un gasto suelto, sigue aquí.
        </div>
    @endif
</div>

<div class="row g-3">
    {{-- 3. Cuenta / medio de pago --}}
    <div class="col-md-6">
        <div class="d-flex align-items-baseline justify-content-between mb-1">
            <label for="account_id" class="form-label fw-semibold mb-0">Cuenta / medio de pago</label>
            <button type="button" class="btn btn-link btn-sm p-0 text-finlia fw-semibold"
                    data-bs-toggle="modal" data-bs-target="#quickAccountModal">
                <i class="bi bi-plus-lg"></i> Nueva cuenta
            </button>
        </div>
        <select id="account_id" name="account_id" class="form-select @error('account_id') is-invalid @enderror"
                data-smart-select="expense_account" required>
            <option value="">Selecciona una cuenta</option>
            @foreach ($accounts as $account)
                <option value="{{ $account->id }}" @selected((string) old('account_id', $expense?->account_id) === (string) $account->id)>{{ $account->name }}</option>
            @endforeach
        </select>
        @error('account_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
    {{-- 4. Fecha --}}
    <div class="col-md-6">
        <x-form-input label="Fecha" name="date" type="date" :value="old('date', $expense?->date?->format('Y-m-d') ?? date('Y-m-d'))" required />
    </div>
</div>

{{-- 5. Descripción --}}
<x-form-input label="Descripción" name="description" :value="$expense?->description" placeholder="Ej: Mercado del mes" />

{{-- Medio de pago y notas: detrás de "Más detalles" para no saturar la pantalla. --}}
<details class="mb-3" data-tour="expense-extra" @if(old('payment_method') || $expense?->payment_method || old('notes') || $expense?->notes) open @endif>
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

{{-- Modal "Nueva cuenta": se empuja al @stack('modals') del layout para
     no anidarse dentro del <form> del gasto. El <form> envuelve todo el
     modal-content (header, body y footer) para que el botón submit del pie
     dispare submit sin depender del atributo HTML `form=`, que en algunos
     navegadores móviles se comía el click cuando el botón vivía fuera. --}}
@push('modals')
    <div class="modal fade" id="quickAccountModal" tabindex="-1" aria-labelledby="quickAccountModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0">
                <form id="quickAccountForm" novalidate data-endpoint="{{ route('accounts.store') }}">
                    @csrf
                    <div class="modal-header border-0">
                        <h5 class="modal-title fw-bold" id="quickAccountModalLabel">
                            <i class="bi bi-plus-circle text-finlia me-1"></i> Nueva cuenta
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="quick_account_name" class="form-label fw-semibold">Nombre</label>
                            <input type="text" name="name" id="quick_account_name" class="form-control"
                                   required minlength="2" maxlength="120" placeholder="Ej: Davivienda ahorros">
                            <div class="invalid-feedback" data-field-error="name"></div>
                        </div>
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label for="quick_account_type" class="form-label fw-semibold">Tipo</label>
                                <select name="type" id="quick_account_type" class="form-select" required>
                                    @foreach (\App\Enums\AccountType::cases() as $case)
                                        <option value="{{ $case->value }}" @selected($case->value === 'cash')>{{ $case->label() }}</option>
                                    @endforeach
                                </select>
                                <div class="invalid-feedback" data-field-error="type"></div>
                            </div>
                            <div class="col-md-6">
                                <label for="quick_account_currency" class="form-label fw-semibold">Moneda</label>
                                <select name="currency" id="quick_account_currency" class="form-select" required>
                                    <option value="COP" selected>COP</option>
                                    <option value="USD">USD</option>
                                    <option value="EUR">EUR</option>
                                </select>
                                <div class="invalid-feedback" data-field-error="currency"></div>
                            </div>
                        </div>
                        <div class="mb-1 mt-3">
                            <label for="quick_account_balance" class="form-label fw-semibold">Saldo inicial</label>
                            <input type="number" name="initial_balance" id="quick_account_balance"
                                   class="form-control" required min="0" step="0.01" value="0">
                            <div class="invalid-feedback" data-field-error="initial_balance"></div>
                            <div class="form-text">Puedes dejarlo en 0 y ajustarlo después.</div>
                        </div>
                        <input type="hidden" name="is_active" value="1">
                        <div class="alert alert-danger small d-none mt-3" data-form-error></div>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-finlia">
                            <span data-submit-label><i class="bi bi-check-lg me-1"></i> Crear y usar</span>
                            <span data-submit-spinner class="d-none">
                                <span class="spinner-border spinner-border-sm me-1"></span> Creando…
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endpush

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
                            select.dispatchEvent(new Event('change', { bubbles: true }));
                        });
                    });
                    select.addEventListener('change', function () { syncChips(select.value); });
                });

                // "Te quedarían $X hasta el DD/MM" tras restar el valor ingresado.
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

                var categorySelect = document.getElementById('category_id');
                var debtsHint = document.querySelector('[data-debts-hint]');
                if (categorySelect && debtsHint) {
                    var debtsId = categorySelect.getAttribute('data-debts-category-id');
                    var refresh = function () {
                        debtsHint.classList.toggle('d-none', String(categorySelect.value) !== String(debtsId));
                    };
                    categorySelect.addEventListener('change', refresh);
                    refresh();
                }

                var form = document.getElementById('quickAccountForm');
                var modalEl = document.getElementById('quickAccountModal');
                var accountSelect = document.getElementById('account_id');
                if (form && modalEl && accountSelect) {
                    var submitBtn = form.querySelector('button[type="submit"]');
                    var submitLabel = form.querySelector('[data-submit-label]');
                    var submitSpinner = form.querySelector('[data-submit-spinner]');
                    var formError = form.querySelector('[data-form-error]');

                    function resetErrors() {
                        formError.classList.add('d-none');
                        formError.textContent = '';
                        form.querySelectorAll('.is-invalid').forEach(function (el) { el.classList.remove('is-invalid'); });
                        form.querySelectorAll('[data-field-error]').forEach(function (el) { el.textContent = ''; });
                    }
                    function showFieldErrors(errors) {
                        Object.keys(errors).forEach(function (field) {
                            var input = form.querySelector('[name="' + field + '"]');
                            var slot = form.querySelector('[data-field-error="' + field + '"]');
                            if (input) input.classList.add('is-invalid');
                            if (slot) slot.textContent = (errors[field] || [])[0] || '';
                        });
                    }

                    form.addEventListener('submit', function (e) {
                        e.preventDefault();
                        resetErrors();
                        submitBtn.disabled = true;
                        submitLabel.classList.add('d-none');
                        submitSpinner.classList.remove('d-none');

                        var payload = new FormData(form);
                        fetch(form.getAttribute('data-endpoint'), {
                            method: 'POST',
                            body: payload,
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            credentials: 'same-origin',
                        })
                            .then(function (res) {
                                return res.json().then(function (body) { return { status: res.status, body: body }; });
                            })
                            .then(function (r) {
                                if (r.status === 201 && r.body.id) {
                                    var opt = document.createElement('option');
                                    opt.value = r.body.id;
                                    opt.textContent = r.body.name;
                                    opt.selected = true;
                                    accountSelect.appendChild(opt);
                                    accountSelect.dispatchEvent(new Event('change', { bubbles: true }));
                                    form.reset();
                                    window.bootstrap?.Modal.getInstance(modalEl)?.hide();
                                } else if (r.status === 422 && r.body.errors) {
                                    showFieldErrors(r.body.errors);
                                } else {
                                    formError.textContent = r.body.message || 'No pudimos crear la cuenta. Inténtalo de nuevo.';
                                    formError.classList.remove('d-none');
                                }
                            })
                            .catch(function () {
                                formError.textContent = 'Fallo de red. Revisa tu conexión.';
                                formError.classList.remove('d-none');
                            })
                            .finally(function () {
                                submitBtn.disabled = false;
                                submitLabel.classList.remove('d-none');
                                submitSpinner.classList.add('d-none');
                            });
                    });
                }
            });
        </script>
    @endpush
@endonce
