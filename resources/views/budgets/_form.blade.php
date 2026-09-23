@php
    /**
     * Campos comunes del formulario de presupuesto.
     * En edición solo se puede cambiar el monto (ver UpdateBudgetRequest).
     */
    $isEdit = isset($budget);
@endphp

@csrf
@if ($isEdit)
    @method('PUT')
@endif

@if ($isEdit)
    <div class="alert alert-light border d-flex gap-2 align-items-center" role="alert">
        <i class="bi bi-info-circle"></i>
        <div class="small">
            Editando el presupuesto de
            <strong>{{ $budget->isTotal() ? 'todo el mes' : $budget->category?->name }}</strong>
            para <strong>{{ $monthLabel }}</strong>.
            Para presupuestar otra categoría u otro mes, crea uno nuevo.
        </div>
    </div>
@else
    <x-form-select
        label="Categoría"
        name="category_id"
        :options="$categories"
        placeholder="Presupuesto total del mes (sin categoría)"
        help="Déjalo en «total» para fijar un techo general, o elige una categoría para limitarla." />

    <div class="row g-3">
        <div class="col-6">
            <x-form-select
                label="Mes"
                name="month"
                :options="[
                    1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
                    5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
                    9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
                ]"
                :selected="$month"
                required />
        </div>
        <div class="col-6">
            <x-form-input label="Año" name="year" type="number" :value="$year" required />
        </div>
    </div>
@endif

<x-form-input
    label="Monto"
    name="amount"
    type="number"
    step="0.01"
    :value="$isEdit ? $budget->amount : null"
    placeholder="800000"
    help="Cuánto quieres poder gastar como máximo en el mes."
    required />

@php
    $envelopeChecked = $isEdit
        ? old('envelope', $budget->envelope) == '1'
        : old('envelope') == '1';
    $envelopeDisabled = $isEdit && $budget->isTotal();
@endphp
<input type="hidden" name="envelope" value="0">
<div class="form-check mb-3">
    <input class="form-check-input" type="checkbox" name="envelope" value="1"
           id="envelope"
           @checked($envelopeChecked)
           @disabled($envelopeDisabled)>
    <label class="form-check-label" for="envelope">
        Apartar del cupo diario (tratar como sobre)
    </label>
    <div class="form-text">
        Márcalo si este presupuesto es un gasto grande y periódico
        (mercado, gasolina, servicios). Su plata se reserva del
        «puedes gastar hoy» y se descuenta del sobre al registrar el
        gasto, en lugar de morder tu cupo del día.
        @if ($envelopeDisabled)
            <br><strong>El presupuesto total del mes no puede ser sobre</strong>: los sobres son por categoría.
        @endif
    </div>
    @error('envelope')
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror
</div>
