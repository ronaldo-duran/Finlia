@extends('layouts.app', ['title' => 'Nuevo recordatorio'])

@php
    // Frecuencias con sentido para un aviso suelto (sin semanal/custom:
    // eso es un gasto recurrente de la Épica 5). Igual que en el índice.
    $frequencies = collect([
        App\Enums\Frequency::Monthly,
        App\Enums\Frequency::Quarterly,
        App\Enums\Frequency::Semester,
        App\Enums\Frequency::Yearly,
    ])->mapWithKeys(fn ($f) => [$f->value => $f->label()])->all();
@endphp

@section('content')
    <x-flash-messages />

    <div class="d-flex align-items-center gap-2 mb-4">
        <a href="{{ route('reminders.index') }}" class="btn btn-icon" aria-label="Volver">
            <i class="bi bi-arrow-left"></i>
        </a>
        <h1 class="h3 mb-0"><i class="bi bi-bell me-2"></i>Nuevo recordatorio</h1>
    </div>

    <div class="row justify-content-center">
        <div class="col-12 col-lg-7">
            <div class="card border-0">
                <div class="card-body">
                    <form method="POST" action="{{ route('reminders.store') }}">
                        @csrf
                        <x-form-input label="De qué te recuerda" name="title" required
                            placeholder="Ej: Tecnomecánica, Renovar pasaporte" />

                        {{-- Input de dinero real con formato en vivo (UI_DESIGN §4). --}}
                        <div class="mb-3">
                            <label for="amount" class="form-label fw-semibold">
                                Cuánto cuesta <span class="text-muted fw-normal">(opcional)</span>
                            </label>
                            <input id="amount" type="text" name="amount" inputmode="decimal"
                                data-money-input placeholder="250000"
                                class="form-control @error('amount') is-invalid @enderror"
                                value="{{ old('amount') }}">
                            @error('amount')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <x-form-input label="Fecha límite" name="due_date" type="date" required
                            help="Puede ser una fecha pasada: el aviso queda como vencido." />

                        <x-form-select label="Se repite" name="frequency"
                            :options="$frequencies" placeholder="No, es de una sola vez" />

                        <div class="mb-3">
                            <label for="notes" class="form-label fw-semibold">
                                Nota <span class="text-muted fw-normal">(opcional)</span>
                            </label>
                            <textarea id="notes" name="notes" rows="2"
                                class="form-control @error('notes') is-invalid @enderror"
                                placeholder="Opcional">{{ old('notes') }}</textarea>
                            @error('notes')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-finlia btn-lg flex-fill">
                                <i class="bi bi-check-lg me-1"></i> Guardar
                            </button>
                            <a href="{{ route('reminders.index') }}" class="btn btn-outline-secondary btn-lg">
                                Cancelar
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
