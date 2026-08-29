@extends('layouts.app', ['title' => 'Deudas'])

@php
    /**
     * Épica 6: panel de deuda.
     * $summary     : totales calculados por DebtService (nada se calcula aquí).
     * $debts       : deudas vigentes, ya ordenadas según la estrategia elegida.
     * $projections : proyección de fin de deuda, indexada por id.
     * $settled     : pagadas o condonadas (historial).
     */
@endphp

@section('content')
    <x-flash-messages />

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-1">
        <h1 class="h3 mb-0"><i class="bi bi-credit-card-2-front me-2"></i>Deudas</h1>
        @if ($summary['count'] > 0)
            <span class="badge bg-finlia-subtle text-finlia border border-finlia rounded-pill px-3 py-2">
                @money($summary['monthly_commitment'])/mes comprometidos
            </span>
        @endif
    </div>
    <p class="text-muted mb-4">
        Tarjetas, préstamos y cuotas. Las cuotas pendientes se descuentan de
        <a href="{{ route('budgets.index') }}">cuánto puedes gastar</a>.
    </p>

    <div class="row g-3">
        {{-- Columna principal --}}
        <div class="col-12 col-lg-8">

            {{-- Resumen --}}
            <div class="row g-3 mb-3">
                <div class="col-12 col-sm-6">
                    <div class="card border-0 h-100"><div class="card-body">
                        <div class="text-muted small text-uppercase">Deuda total</div>
                        <div class="fs-4 fw-bold text-danger">@money($summary['total_balance'])</div>
                        @if ($summary['total_original'] > 0)
                            <div class="progress mt-2" style="height:.5rem" role="progressbar"
                                 aria-label="Progreso de pago" aria-valuenow="{{ $summary['progress_percent'] }}"
                                 aria-valuemin="0" aria-valuemax="100">
                                <div class="progress-bar bg-success" style="width: {{ $summary['progress_percent'] }}%"></div>
                            </div>
                            <div class="small text-muted mt-1">
                                Llevas @money($summary['total_paid']) pagados
                                ({{ str_replace('.', ',', (string) $summary['progress_percent']) }} %)
                            </div>
                        @endif
                    </div></div>
                </div>
                <div class="col-12 col-sm-6">
                    <div class="card border-0 h-100"><div class="card-body">
                        <div class="text-muted small text-uppercase">Pago mensual comprometido</div>
                        <div class="fs-4 fw-bold">@money($summary['monthly_commitment'])</div>
                        <div class="small text-muted mt-1">
                            {{ $summary['count'] }} {{ $summary['count'] === 1 ? 'deuda activa' : 'deudas activas' }}
                        </div>
                    </div></div>
                </div>
            </div>

            {{-- Estrategia de pago --}}
            @if ($debts->count() > 1)
                <div class="card border-0 mb-3"><div class="card-body">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                        <div>
                            <div class="fw-semibold"><i class="bi bi-sort-down me-1"></i>Orden sugerido</div>
                            <div class="small text-muted">{{ $strategy->description() }}</div>
                        </div>
                        <div class="btn-group btn-group-sm" role="group" aria-label="Estrategia">
                            @foreach ($strategies as $case)
                                <a href="{{ route('debts.index', ['estrategia' => $case->value]) }}"
                                   class="btn {{ $strategy === $case ? 'btn-finlia' : 'btn-outline-secondary' }}">
                                    {{ $case->label() }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                </div></div>
            @endif

            {{-- Listado --}}
            @forelse ($debts as $debt)
                @php $projection = $projections[$debt->id] ?? null; @endphp
                <div class="card border-0 mb-2"><div class="card-body">
                    <div class="d-flex justify-content-between align-items-start gap-2">
                        <div class="flex-grow-1">
                            <a href="{{ route('debts.show', $debt) }}" class="text-decoration-none">
                                <span class="fw-semibold">
                                    <i class="bi {{ $debt->type->icon() }} me-1"></i>{{ $debt->name }}
                                </span>
                            </a>
                            <span class="badge {{ $debt->status->badgeClass() }} ms-1">{{ $debt->status->label() }}</span>
                            <div class="small text-muted mt-1">
                                {{ $debt->type->label() }}
                                @if ($debt->institution) · {{ $debt->institution }} @endif
                                @if ($debt->interest_rate !== null)
                                    · {{ str_replace('.', ',', rtrim(rtrim(number_format((float) $debt->interest_rate, 3, '.', ''), '0'), '.')) }} % anual
                                @endif
                                @if ($debt->due_day) · paga el día {{ $debt->due_day }} @endif
                            </div>
                        </div>
                        <div class="text-end">
                            <div class="fw-bold text-danger">@money($debt->current_balance)</div>
                            <div class="small text-muted">de @money($debt->original_amount)</div>
                        </div>
                    </div>

                    <div class="progress mt-2" style="height:.4rem" role="progressbar"
                         aria-label="Progreso de {{ $debt->name }}" aria-valuenow="{{ $debt->progressPercent() }}"
                         aria-valuemin="0" aria-valuemax="100">
                        <div class="progress-bar bg-success" style="width: {{ $debt->progressPercent() }}%"></div>
                    </div>

                    @if ($projection !== null)
                        <div class="small text-muted mt-2">
                            @if ($projection['never_ends'])
                                <i class="bi bi-exclamation-triangle me-1"></i>
                                @if ($debt->monthlyCommitment() <= 0)
                                    Sin cuota registrada no se puede estimar cuándo terminarías.
                                @else
                                    A este ritmo la cuota no cubre los intereses: el saldo no bajaría.
                                @endif
                            @elseif ($projection['months'] !== null)
                                <i class="bi bi-flag me-1"></i>
                                Si mantienes este ritmo, terminarías hacia
                                <strong>{{ $projection['date']->locale('es')->isoFormat('MMMM [de] YYYY') }}</strong>
                                ({{ $projection['months'] }} {{ $projection['months'] === 1 ? 'mes' : 'meses' }}).
                            @endif
                        </div>
                    @endif
                </div></div>
            @empty
                <div class="card border-0"><div class="card-body text-center py-5">
                    <i class="bi bi-emoji-smile fs-1 text-muted"></i>
                    <p class="mb-1 mt-2 fw-semibold">No tienes deudas registradas</p>
                    <p class="text-muted small mb-0">
                        Si tienes una tarjeta o un préstamo, regístralo para ver cuánto te queda y cuándo terminarías.
                    </p>
                </div></div>
            @endforelse

            {{-- Historial de deudas saldadas --}}
            @if ($settled->isNotEmpty())
                <h2 class="h6 text-muted text-uppercase mt-4 mb-2">Ya no pesan</h2>
                @foreach ($settled as $debt)
                    <div class="card border-0 mb-2"><div class="card-body py-2">
                        <div class="d-flex justify-content-between align-items-center gap-2">
                            <a href="{{ route('debts.show', $debt) }}" class="text-decoration-none text-body">
                                <i class="bi {{ $debt->type->icon() }} me-1"></i>{{ $debt->name }}
                            </a>
                            <span class="badge {{ $debt->status->badgeClass() }}">{{ $debt->status->label() }}</span>
                        </div>
                    </div></div>
                @endforeach
            @endif
        </div>

        {{-- Columna: alta --}}
        <div class="col-12 col-lg-4">
            <div class="card border-0">
                <div class="card-header border-0 bg-transparent fw-semibold">
                    <i class="bi bi-plus-circle me-1"></i> Registrar deuda
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('debts.store') }}">
                        @csrf
                        @include('debts._form', ['debt' => null, 'prefix' => 'new_'])
                        <button type="submit" class="btn btn-finlia w-100 mt-3">Guardar deuda</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <p class="text-muted small mt-4 mb-0">
        <i class="bi bi-info-circle me-1"></i>
        Las fechas de fin son <strong>estimaciones</strong>: suponen que mantienes la cuota
        y no contemplan cuotas de manejo, seguros, mora ni compras nuevas.
    </p>
@endsection
