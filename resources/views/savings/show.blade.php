@extends('layouts.app', ['title' => $goal->name])

@php
    /**
     * Épica 7: detalle de una meta de ahorro.
     * $goal           : la meta.
     * $contributions  : historial de aportes y retiros (desc por fecha).
     * $recommendation : aporte mensual recomendado (SavingsGoalService).
     */
@endphp

@section('content')
    <x-flash-messages />

    <div class="d-flex align-items-center gap-2 mb-1">
        <a href="{{ route('savings-goals.index') }}" class="btn btn-icon" aria-label="Volver">
            <i class="bi bi-arrow-left"></i>
        </a>
        <div>
            <h1 class="h4 mb-0">
                <i class="bi {{ $goal->is_emergency_fund ? 'bi-shield-check' : 'bi-flag' }} me-2"></i>{{ $goal->name }}
            </h1>
            <div class="small text-muted">
                @if ($goal->priority)
                    Prioridad {{ strtolower($goal->priority->label()) }}
                @endif
                @if ($goal->target_date)
                    @if ($goal->priority) · @endif
                    Objetivo: {{ $goal->target_date->locale('es')->isoFormat('MMMM [de] YYYY') }}
                @endif
            </div>
        </div>
        <span class="badge {{ $goal->status->badgeClass() }} ms-auto">{{ $goal->status->label() }}</span>
    </div>

    {{-- Cifra protagonista: lo ahorrado (UI_DESIGN §1.4) --}}
    <div class="card border-0 bg-finlia-subtle mb-3"><div class="card-body py-4">
        <div class="d-flex justify-content-between align-items-end flex-wrap gap-2">
            <div>
                <div class="text-muted small text-uppercase">Ahorrado</div>
                <div class="hero-figure">@money($goal->current_amount)</div>
            </div>
            <div class="text-end">
                <div class="small text-muted">Objetivo @money($goal->target_amount)</div>
                <div class="small text-muted">Faltan @money($goal->remainingAmount())</div>
            </div>
        </div>
        <div class="progress mt-3" style="height:.6rem" role="progressbar"
             aria-label="Progreso" aria-valuenow="{{ $goal->progressPercent() }}"
             aria-valuemin="0" aria-valuemax="100">
            <div class="progress-bar bg-success" style="width: {{ $goal->progressPercent() }}%"></div>
        </div>
        <div class="small text-muted mt-1">
            {{ str_replace('.', ',', (string) $goal->progressPercent()) }} % de la meta
        </div>
    </div></div>

    {{-- Recomendación (estimación) y avisos de estado --}}
    <div class="card border-0 mb-3"><div class="card-body">
        @if ($recommendation['possible'] && $recommendation['amount'] > 0)
            <div class="fw-semibold">
                <i class="bi bi-graph-up-arrow me-1"></i>
                Aportando @money($recommendation['amount']) al mes la logras
                @if ($recommendation['months'] === 1) este mes
                @else en {{ $recommendation['months'] }} meses @endif
            </div>
            <div class="form-text">
                Es una estimación. Para programar ese aporte, edítalo en
                "aporte mensual que destinarás".
            </div>
        @endif

        @if ($goal->isOverdue())
            <div class="alert alert-warning py-2 mb-0 mt-2 small">
                <i class="bi bi-clock-history me-1"></i>
                La fecha objetivo pasó: márcala como lograda si la cumples,
                archívala o aplaza la fecha al editarla.
            </div>
        @endif
    </div></div>

    {{-- Acciones de estado --}}
    @if (in_array($goal->status, [\App\Enums\SavingsGoalStatus::Active, \App\Enums\SavingsGoalStatus::Paused]))
        <div class="d-flex flex-wrap gap-2 mb-3">
            @if ($goal->status->value === 'active')
                <form action="{{ route('savings-goals.pause', $goal) }}" method="POST">
                    @csrf
                    <button type="submit" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-pause-circle me-1"></i> Pausar
                    </button>
                </form>
            @else
                <form action="{{ route('savings-goals.resume', $goal) }}" method="POST">
                    @csrf
                    <button type="submit" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-play-circle me-1"></i> Reactivar
                    </button>
                </form>
            @endif
            <form action="{{ route('savings-goals.complete', $goal) }}" method="POST">
                @csrf
                <button type="submit" class="btn btn-outline-success btn-sm">
                    <i class="bi bi-trophy me-1"></i> Marcar lograda
                </button>
            </form>
            <form action="{{ route('savings-goals.archive', $goal) }}" method="POST">
                @csrf
                <button type="submit" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-archive me-1"></i> Archivar
                </button>
            </form>
            <a href="{{ route('savings-goals.edit', $goal) }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-pencil me-1"></i> Editar
            </a>
        </div>
    @elseif ($goal->status->value === 'completed')
        <div class="d-flex flex-wrap gap-2 mb-3">
            <form action="{{ route('savings-goals.archive', $goal) }}" method="POST">
                @csrf
                <button type="submit" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-archive me-1"></i> Archivar
                </button>
            </form>
        </div>
    @endif

    {{-- Registrar aporte o retiro (solo metas que los aceptan) --}}
    @if ($goal->status->acceptsContributions())
        <div class="card border-0 mb-3"><div class="card-body">
            <h2 class="h6 text-muted text-uppercase mb-3">Registrar movimiento</h2>
            <form method="POST" action="{{ route('savings-goals.contributions.store', $goal) }}">
                @csrf
                <div class="row g-2">
                    <div class="col-12 col-sm-4">
                        <label for="c_type" class="form-label small fw-semibold">Tipo</label>
                        <select name="type" id="c_type" required
                                class="form-select @error('type') is-invalid @enderror">
                            @foreach (\App\Enums\SavingsGoalContributionType::cases() as $case)
                                <option value="{{ $case->value }}" @selected(old('type') === $case->value)>{{ $case->label() }}</option>
                            @endforeach
                        </select>
                        @error('type')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-6 col-sm-4">
                        <label for="c_amount" class="form-label small fw-semibold">Monto</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="text" inputmode="decimal" data-money-input
                                   name="amount" id="c_amount" required
                                   class="form-control @error('amount') is-invalid @enderror"
                                   value="{{ old('amount') }}">
                        </div>
                        @error('amount')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-6 col-sm-4">
                        <label for="c_date" class="form-label small fw-semibold">Fecha</label>
                        <input type="date" name="date" id="c_date" required
                               max="{{ now(config('app.timezone'))->toDateString() }}"
                               class="form-control @error('date') is-invalid @enderror"
                               value="{{ old('date', now(config('app.timezone'))->toDateString()) }}">
                        @error('date')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label for="c_notes" class="form-label small fw-semibold">
                            Nota <span class="text-muted fw-normal">(opcional)</span>
                        </label>
                        <input type="text" name="notes" id="c_notes" maxlength="2000"
                               class="form-control" value="{{ old('notes') }}">
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-finlia">
                            <i class="bi bi-check-lg me-1"></i> Registrar
                        </button>
                    </div>
                </div>
            </form>
        </div></div>
    @endif

    {{-- Historial --}}
    <h2 class="h6 text-muted text-uppercase mt-4 mb-2">Historial de movimientos</h2>
    @forelse ($contributions as $c)
        <div class="card border-0 mb-1"><div class="card-body py-2">
            <div class="d-flex justify-content-between align-items-center gap-2">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle d-inline-flex align-items-center justify-content-center"
                         style="width:2rem;height:2rem; @if ($c->type->value === 'deposit') background:rgba(var(--finlia-success-rgb), .12); color:var(--finlia-success) @else background:rgba(var(--finlia-danger-rgb), .12); color:var(--finlia-danger) @endif">
                        <i class="bi {{ $c->type->icon() }}"></i>
                    </div>
                    <div>
                        <div class="fw-semibold small">{{ $c->type->label() }}</div>
                        <div class="small text-muted">
                            {{ $c->date->locale('es')->isoFormat('D [de] MMMM [de] YYYY') }}
                            @if ($c->notes) · {{ $c->notes }} @endif
                        </div>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <div class="fw-bold {{ $c->type->value === 'deposit' ? 'text-success' : 'text-danger' }}">
                        {{ $c->type->value === 'deposit' ? '+' : '−' }}@money($c->amount)
                    </div>
                    <form action="{{ route('savings-goals.contributions.destroy', [$goal, $c]) }}" method="POST">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-icon btn-sm" aria-label="Eliminar movimiento">
                            <i class="bi bi-trash"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div></div>
    @empty
        <p class="text-muted small">
            Sin movimientos todavía: registra el primer aporte (o lo que ya tenías
            ahorrado) arriba.
        </p>
    @endforelse
@endsection
