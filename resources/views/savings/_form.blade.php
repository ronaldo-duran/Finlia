{{--
    Alta y edición de una meta de ahorro (Épica 7).
    El dinero usa data-money-input (docs/UI_DESIGN.md §4), nunca type="number".
--}}
@php($goal = $goal ?? null)

<div class="row g-2 mb-4">
    <div class="col-12">
        <label for="{{ $prefix }}name" class="form-label small fw-semibold">Nombre</label>
        <input type="text" name="name" id="{{ $prefix }}name" required maxlength="120"
               class="form-control @error('name') is-invalid @enderror"
               placeholder="Fondo de emergencia, Viaje…"
               value="{{ old('name', $goal?->name) }}">
        @error('name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>

    <div class="col-6">
        <label for="{{ $prefix }}target_amount" class="form-label small fw-semibold">Cuánto quieres ahorrar</label>
        <div class="input-group">
            <span class="input-group-text">$</span>
            <input type="text" inputmode="decimal" data-money-input
                   name="target_amount" id="{{ $prefix }}target_amount" required
                   class="form-control @error('target_amount') is-invalid @enderror"
                   value="{{ old('target_amount', $goal?->target_amount) }}">
        </div>
        @error('target_amount')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>

    <div class="col-6">
        <label for="{{ $prefix }}target_date" class="form-label small fw-semibold">Fecha objetivo <span class="text-muted fw-normal">(opcional)</span></label>
        <input type="date" name="target_date" id="{{ $prefix }}target_date"
               class="form-control @error('target_date') is-invalid @enderror"
               value="{{ old('target_date', $goal?->target_date?->format('Y-m-d')) }}">
        <div class="form-text">Sin fecha la meta es abierta (típico del fondo de emergencia).</div>
        @error('target_date')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>

    <div class="col-6">
        <label for="{{ $prefix }}priority" class="form-label small fw-semibold">Prioridad</label>
        <select name="priority" id="{{ $prefix }}priority" class="form-select">
            <option value="">—</option>
            @foreach (\App\Enums\SavingsGoalPriority::cases() as $case)
                <option value="{{ $case->value }}" @selected(old('priority', $goal?->priority?->value) === $case->value)>{{ $case->label() }}</option>
            @endforeach
        </select>
    </div>
</div>

<h2 class="h6 text-muted text-uppercase mb-2">2. Tu plan de ahorro</h2>
<div class="row g-2">
    <div class="col-12">
        <label for="{{ $prefix }}monthly_commitment" class="form-label small fw-semibold">
            Aporte mensual que destinarás <span class="text-muted fw-normal">(opcional)</span>
        </label>
        <div class="input-group">
            <span class="input-group-text">$</span>
            <input type="text" inputmode="decimal" data-money-input
                   name="monthly_commitment" id="{{ $prefix }}monthly_commitment"
                   class="form-control @error('monthly_commitment') is-invalid @enderror"
                   value="{{ old('monthly_commitment', $goal?->monthly_commitment) }}">
        </div>
        <div class="form-text">
            Es lo que reservas cada mes para esta meta: se descuenta de
            <a href="{{ route('budgets.index') }}">cuánto puedes gastar</a>. Si lo dejas
            vacío, la meta no compromete presupuesto.
        </div>
        @error('monthly_commitment')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>

    <div class="col-12">
        <div class="form-check">
            <input class="form-check-input" type="checkbox" value="1"
                   name="is_emergency_fund" id="{{ $prefix }}emergency"
                   @checked(old('is_emergency_fund', $goal?->is_emergency_fund))>
            <label class="form-check-label small" for="{{ $prefix }}emergency">
                Es el fondo de emergencia del hogar
            </label>
        </div>
    </div>

    <div class="col-12">
        <label for="{{ $prefix }}notes" class="form-label small fw-semibold">
            Notas <span class="text-muted fw-normal">(opcional)</span>
        </label>
        <textarea name="notes" id="{{ $prefix }}notes" rows="2" maxlength="2000"
                  class="form-control">{{ old('notes', $goal?->notes) }}</textarea>
    </div>
</div>
