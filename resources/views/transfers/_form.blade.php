{{--
    Formulario de transferencia entre cuentas (Épica 10, ADR-0035).
    Usado tanto en create como en edit.
--}}
@php
    $isEdit = isset($transfer);
@endphp

@if ($errors->any())
    <div class="alert alert-danger mb-3">
        <ul class="mb-0 ps-3">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

{{-- Origen → Destino --}}
<div class="row g-3 mb-3">
    <div class="col-12 col-md-6">
        <label class="form-label fw-semibold" for="from_account_id">
            <i class="bi bi-box-arrow-right me-1"></i>Cuenta origen
        </label>
        <select name="from_account_id" id="from_account_id" class="form-select" required
                data-smart-select="transfer_from_account">
            <option value="">Selecciona una cuenta…</option>
            @foreach ($accounts as $account)
                <option value="{{ $account->id }}"
                    @selected(old('from_account_id', $isEdit ? $transfer->from_account_id : null) == $account->id)>
                    {{ $account->name }} ({{ money((float) $account->current_balance) }})
                </option>
            @endforeach
        </select>
    </div>
    <div class="col-12 col-md-6">
        <label class="form-label fw-semibold" for="to_account_id">
            <i class="bi bi-box-arrow-in-left me-1"></i>Cuenta destino
        </label>
        <select name="to_account_id" id="to_account_id" class="form-select" required
                data-smart-select="transfer_to_account">
            <option value="">Selecciona una cuenta…</option>
            @foreach ($accounts as $account)
                <option value="{{ $account->id }}"
                    @selected(old('to_account_id', $isEdit ? $transfer->to_account_id : null) == $account->id)>
                    {{ $account->name }}
                </option>
            @endforeach
        </select>
    </div>
</div>

{{-- Monto y fecha --}}
<div class="row g-3 mb-3">
    <div class="col-12 col-md-6">
        <label class="form-label fw-semibold" for="amount">
            <i class="bi bi-cash me-1"></i>Monto
        </label>
        <div class="input-group">
            <span class="input-group-text">$</span>
            <input type="text" inputmode="decimal" name="amount" id="amount"
                   class="form-control"
                   value="{{ old('amount', $isEdit ? $transfer->amount : '') }}"
                   placeholder="0" required
                   data-money-input>
        </div>
    </div>
    <div class="col-12 col-md-6">
        <label class="form-label fw-semibold" for="date">
            <i class="bi bi-calendar3 me-1"></i>Fecha
        </label>
        <input type="date" name="date" id="date" class="form-control"
               value="{{ old('date', $isEdit ? $transfer->date->format('Y-m-d') : now()->format('Y-m-d')) }}"
               max="{{ now()->format('Y-m-d') }}" required>
    </div>
</div>

{{-- Descripción --}}
<div class="mb-3">
    <label class="form-label" for="description">
        <i class="bi bi-chat-left-text me-1"></i>Descripción <span class="text-muted">(opcional)</span>
    </label>
    <input type="text" name="description" id="description" class="form-control"
           value="{{ old('description', $isEdit ? $transfer->description : '') }}"
           placeholder="Ej. Paso de ahorros a nómina"
           maxlength="200">
</div>

{{-- Notas (secundario, oculto por defecto) --}}
<details {{ old('notes', $isEdit && $transfer->notes ? 'open' : '') !== '' ? 'open' : '' }}>
    <summary class="text-muted small mb-2" style="cursor: pointer;">Más detalles</summary>
    <div class="mb-3 mt-2">
        <label class="form-label" for="notes">Notas internas</label>
        <textarea name="notes" id="notes" class="form-control" rows="3"
                  maxlength="2000" placeholder="Solo para ti…">{{ old('notes', $isEdit ? $transfer->notes : '') }}</textarea>
    </div>
</details>
