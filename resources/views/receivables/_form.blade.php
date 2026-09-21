{{--
    Alta y edición de una cuenta por cobrar. Espejo simplificado de la
    deuda: aquí no hay tasa, ni cuota, ni plazo. Un importe, un deudor y
    (opcional) una fecha tentativa de cobro.

    El dinero usa `data-money-input` (docs/UI_DESIGN.md), nunca type="number".
--}}
@php($receivable = $receivable ?? null)

<h2 class="h6 text-muted text-uppercase mb-2">1. Quién te debe</h2>
<div class="row g-2 mb-4">
    <div class="col-12">
        <label for="{{ $prefix }}debtor_name" class="form-label small fw-semibold">Nombre del deudor</label>
        <input type="text" name="debtor_name" id="{{ $prefix }}debtor_name" required maxlength="120"
               class="form-control @error('debtor_name') is-invalid @enderror"
               placeholder="María López, Cliente Empresa X…"
               value="{{ old('debtor_name', $receivable?->debtor_name) }}">
        @error('debtor_name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>

    @if ($members->isNotEmpty())
        <div class="col-12">
            <label for="{{ $prefix }}debtor_user_id" class="form-label small fw-semibold">
                ¿Es alguien del hogar? <span class="text-muted fw-normal">(opcional)</span>
            </label>
            <select name="debtor_user_id" id="{{ $prefix }}debtor_user_id" class="form-select">
                <option value="">Nadie del hogar</option>
                @foreach ($members as $member)
                    <option value="{{ $member->id }}" @selected((int) old('debtor_user_id', $receivable?->debtor_user_id) === $member->id)>
                        {{ $member->name }}
                    </option>
                @endforeach
            </select>
            <div class="form-text">Solo si el deudor es miembro de este hogar (p. ej. te está pagando un préstamo).</div>
        </div>
    @endif
</div>

<h2 class="h6 text-muted text-uppercase mb-2">2. Cuánto y para cuándo</h2>
<div class="row g-2 mb-4">
    <div class="col-12">
        <label for="{{ $prefix }}name" class="form-label small fw-semibold">Concepto</label>
        <input type="text" name="name" id="{{ $prefix }}name" required maxlength="120"
               class="form-control @error('name') is-invalid @enderror"
               placeholder="Préstamo personal, Trabajo facturado…"
               value="{{ old('name', $receivable?->name) }}">
        @error('name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>

    <div class="col-12 col-sm-6">
        <label for="{{ $prefix }}original_amount" class="form-label small fw-semibold">Monto</label>
        <div class="input-group">
            <span class="input-group-text">$</span>
            <input type="text" inputmode="decimal" data-money-input
                   name="original_amount" id="{{ $prefix }}original_amount" required
                   class="form-control @error('original_amount') is-invalid @enderror"
                   value="{{ old('original_amount', $receivable?->original_amount) }}">
        </div>
        @error('original_amount')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>

    <div class="col-12 col-sm-6">
        <label for="{{ $prefix }}due_date" class="form-label small fw-semibold">
            Fecha tentativa de cobro <span class="text-muted fw-normal">(opcional)</span>
        </label>
        <input type="date" name="due_date" id="{{ $prefix }}due_date"
               class="form-control @error('due_date') is-invalid @enderror"
               value="{{ old('due_date', $receivable?->due_date?->format('Y-m-d')) }}">
        <div class="form-text">Cuando llegue esta fecha te aparece en recordatorios.</div>
        @error('due_date')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
</div>

<h2 class="h6 text-muted text-uppercase mb-2">3. Detalles</h2>
<div class="row g-2">
    @if ($receivable !== null)
        <div class="col-12 col-sm-6">
            <label for="{{ $prefix }}status" class="form-label small fw-semibold">Estado</label>
            <select name="status" id="{{ $prefix }}status" class="form-select">
                @foreach (\App\Enums\ReceivableStatus::cases() as $case)
                    <option value="{{ $case->value }}" @selected(old('status', $receivable->status->value) === $case->value)>
                        {{ $case->label() }}
                    </option>
                @endforeach
            </select>
            <div class="form-text">«Dado por perdido» cierra la cuenta sin registrar ingreso.</div>
        </div>
    @endif

    <div class="col-12">
        <label for="{{ $prefix }}description" class="form-label small fw-semibold">
            Descripción <span class="text-muted fw-normal">(opcional)</span>
        </label>
        <textarea name="description" id="{{ $prefix }}description" rows="2" maxlength="2000"
                  class="form-control @error('description') is-invalid @enderror">{{ old('description', $receivable?->description) }}</textarea>
        @error('description')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>

    <div class="col-12">
        <label for="{{ $prefix }}notes" class="form-label small fw-semibold">
            Notas <span class="text-muted fw-normal">(opcional)</span>
        </label>
        <textarea name="notes" id="{{ $prefix }}notes" rows="2" maxlength="2000"
                  class="form-control @error('notes') is-invalid @enderror">{{ old('notes', $receivable?->notes) }}</textarea>
        @error('notes')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
</div>
