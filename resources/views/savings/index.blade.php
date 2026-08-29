@extends('layouts.app', ['title' => 'Metas de ahorro'])

@php
    /**
     * Épica 7: panel de metas de ahorro.
     * $summary : totales calculados por SavingsGoalService (nada se calcula aquí).
     * $goals   : metas del filtro de estado elegido (chips).
     * $estado  : vigentes | logradas | archivadas.
     */
@endphp

@section('content')
    <x-flash-messages />

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-1">
        <h1 class="h3 mb-0"><i class="bi bi-piggy-bank me-2"></i>Metas de ahorro</h1>
        <a href="{{ route('savings-goals.create') }}" class="btn btn-finlia w-100 w-sm-auto">
            <i class="bi bi-plus-lg me-1"></i> Nueva meta
        </a>
    </div>
    <p class="text-muted mb-4">
        Convierte objetivos en algo medible. El aporte programado se descuenta de
        <a href="{{ route('budgets.index') }}">cuánto puedes gastar</a>.
    </p>

    {{-- Filtro de estado: chips (atajo) + control real por query string --}}
    <div class="chip-row mb-3">
        @foreach (['vigentes' => 'Vigentes', 'logradas' => 'Logradas', 'archivadas' => 'Archivadas'] as $key => $label)
            <a href="{{ route('savings-goals.index', ['estado' => $key]) }}"
               class="chip {{ $estado === $key ? 'active' : '' }}">{{ $label }}</a>
        @endforeach
    </div>

    @if ($estado === 'vigentes')
        {{-- Resumen solo en vigentes: logradas/archivadas son historia --}}
        <div class="row g-3 mb-3">
            <div class="col-12 col-sm-6">
                <div class="card border-0 h-100"><div class="card-body">
                    <div class="text-muted small text-uppercase">Ahorrado en metas</div>
                    <div class="fs-4 fw-bold">@money($summary['total_saved'])</div>
                    <div class="small text-muted mt-1">
                        de @money($summary['total_target'])
                        ({{ str_replace('.', ',', (string) $summary['progress_percent']) }} %)
                    </div>
                    @if ($summary['total_target'] > 0)
                        <div class="progress mt-2" style="height:.5rem" role="progressbar"
                             aria-label="Progreso total" aria-valuenow="{{ $summary['progress_percent'] }}"
                             aria-valuemin="0" aria-valuemax="100">
                            <div class="progress-bar bg-success" style="width: {{ $summary['progress_percent'] }}%"></div>
                        </div>
                    @endif
                </div></div>
            </div>
            <div class="col-12 col-sm-6">
                <div class="card border-0 h-100"><div class="card-body">
                    <div class="text-muted small text-uppercase">Aporte mensual programado</div>
                    <div class="fs-4 fw-bold">@money($summary['monthly_commitment'])</div>
                    <div class="small text-muted mt-1">
                        @if ($summary['overdue_count'] > 0)
                            <span class="text-danger"><i class="bi bi-exclamation-triangle me-1"></i>
                            {{ $summary['overdue_count'] }} meta{{ $summary['overdue_count'] === 1 ? '' : 's' }} vencida{{ $summary['overdue_count'] === 1 ? '' : 's' }}</span>
                        @else
                            {{ $summary['count'] }} meta{{ $summary['count'] === 1 ? '' : 's' }} vigente{{ $summary['count'] === 1 ? '' : 's' }}
                        @endif
                    </div>
                </div></div>
            </div>
        </div>
    @endif

    @forelse ($goals as $goal)
        <div class="card border-0 mb-2"><div class="card-body">
            <div class="d-flex justify-content-between align-items-start gap-2">
                <div class="flex-grow-1">
                    <a href="{{ route('savings-goals.show', $goal) }}" class="text-decoration-none">
                        <span class="fw-semibold">
                            <i class="bi {{ $goal->is_emergency_fund ? 'bi-shield-check' : 'bi-flag' }} me-1"></i>{{ $goal->name }}
                        </span>
                    </a>
                    <span class="badge {{ $goal->status->badgeClass() }} ms-1">{{ $goal->status->label() }}</span>
                    @if ($goal->priority)
                        <span class="badge {{ $goal->priority->badgeClass() }} ms-1">{{ $goal->priority->label() }}</span>
                    @endif
                    @if ($goal->isOverdue())
                        <span class="badge text-bg-danger ms-1"><i class="bi bi-clock-history me-1"></i>Vencida</span>
                    @endif
                    <div class="small text-muted mt-1">
                        Objetivo @money($goal->target_amount)
                        @if ($goal->target_date)
                            · {{ $goal->target_date->locale('es')->isoFormat('MMM YYYY') }}
                        @endif
                        @if ($goal->monthly_commitment)
                            · @money($goal->monthly_commitment)/mes
                        @endif
                    </div>
                </div>
                <div class="text-end">
                    <div class="fw-bold">@money($goal->current_amount)</div>
                    <div class="small text-muted">
                        faltan @money($goal->remainingAmount())
                    </div>
                </div>
            </div>

            <div class="progress mt-2" style="height:.4rem" role="progressbar"
                 aria-label="Progreso de {{ $goal->name }}" aria-valuenow="{{ $goal->progressPercent() }}"
                 aria-valuemin="0" aria-valuemax="100">
                <div class="progress-bar bg-success" style="width: {{ $goal->progressPercent() }}%"></div>
            </div>
        </div></div>
    @empty
        <div class="card border-0"><div class="card-body text-center py-5">
            <i class="bi bi-emoji-smile fs-1 text-muted"></i>
            <p class="mb-1 mt-2 fw-semibold">
                @if ($estado === 'vigentes')
                    No tienes metas de ahorro activas
                @elseif ($estado === 'logradas')
                    Aún no hay metas logradas
                @else
                    No hay metas archivadas
                @endif
            </p>
            @if ($estado === 'vigentes')
                <p class="text-muted small mb-3">
                    ¿Viaje, fondo de emergencia, cuota inicial? Ponle cifra y fecha:
                    te diremos cuánto apartar cada mes.
                </p>
                <a href="{{ route('savings-goals.create') }}" class="btn btn-finlia">
                    <i class="bi bi-plus-lg me-1"></i> Nueva meta
                </a>
            @endif
        </div></div>
    @endforelse
@endsection
