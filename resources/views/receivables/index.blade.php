@extends('layouts.app', ['title' => 'Cuentas por cobrar'])

@php
    /**
     * Épica 15: panel de cuentas por cobrar.
     * $summary      : totales calculados por ReceivableService.
     * $receivables  : cuentas pendientes/parciales ordenadas por fecha de cobro.
     * $settled      : cobradas o dadas por perdidas (historial).
     */
    use App\Enums\ReceivableStatus;
    $hoy = now(config('app.timezone'))->startOfDay();
@endphp

@section('content')
    <x-flash-messages />

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-1">
        <h1 class="h3 mb-0"><i class="bi bi-inbox-fill me-2"></i>Cuentas por cobrar</h1>
        <a href="{{ route('receivables.create') }}" class="btn btn-finlia w-100 w-sm-auto">
            <i class="bi bi-plus-lg me-1"></i> Registrar cobro
        </a>
    </div>
    <p class="text-muted mb-4">
        Dinero que te deben. Cuando el cobro entre a una cuenta, se registra el ingreso automáticamente.
    </p>

    <div class="row g-3">
        <div class="col-12">

            {{-- Resumen --}}
            <div class="row g-3 mb-3">
                <div class="col-12 col-sm-6">
                    <div class="card border-0 h-100"><div class="card-body">
                        <div class="text-muted small text-uppercase">Por cobrar</div>
                        <div class="fs-4 fw-bold text-finlia">@money($summary['total_balance'])</div>
                        @if ($summary['total_original'] > 0)
                            <div class="progress mt-2" style="height:.5rem" role="progressbar"
                                 aria-label="Progreso de cobro" aria-valuenow="{{ $summary['progress_percent'] }}"
                                 aria-valuemin="0" aria-valuemax="100">
                                <div class="progress-bar bg-success" style="width: {{ $summary['progress_percent'] }}%"></div>
                            </div>
                            <div class="small text-muted mt-1">
                                Ya cobraste @money($summary['total_collected'])
                                (@percent($summary['progress_percent']))
                            </div>
                        @endif
                    </div></div>
                </div>
                <div class="col-12 col-sm-6">
                    <div class="card border-0 h-100"><div class="card-body">
                        <div class="text-muted small text-uppercase">Cuentas activas</div>
                        <div class="fs-4 fw-bold">{{ $summary['count'] }}</div>
                        <div class="small text-muted mt-1">
                            {{ $summary['count'] === 1 ? 'cobro pendiente' : 'cobros pendientes' }}
                        </div>
                    </div></div>
                </div>
            </div>

            {{-- Listado --}}
            @forelse ($receivables as $receivable)
                @php
                    $due = $receivable->due_date;
                    $overdue = $due !== null && $due->lt($hoy);
                @endphp
                <div class="card border-0 mb-2" data-tour="receivables-list"><div class="card-body">
                    <div class="d-flex justify-content-between align-items-start gap-2">
                        <div class="flex-grow-1">
                            <a href="{{ route('receivables.show', $receivable) }}" class="text-decoration-none">
                                <span class="fw-semibold">
                                    <i class="bi bi-person-check me-1"></i>{{ $receivable->debtor_name }}
                                </span>
                            </a>
                            <span class="badge {{ $receivable->status->badgeClass() }} ms-1">{{ $receivable->status->label() }}</span>
                            <div class="small text-muted mt-1">
                                {{ $receivable->name }}
                                @if ($due)
                                    · {{ $overdue ? 'vencido el' : 'para el' }}
                                    <span class="{{ $overdue ? 'text-danger fw-semibold' : '' }}">
                                        {{ $due->format('d/m/Y') }}
                                    </span>
                                @endif
                            </div>
                        </div>
                        <div class="text-end">
                            <div class="fw-bold text-finlia">@money($receivable->current_balance)</div>
                            <div class="small text-muted">de @money($receivable->original_amount)</div>
                        </div>
                    </div>

                    <div class="progress mt-2" style="height:.4rem" role="progressbar"
                         aria-label="Progreso de cobro de {{ $receivable->name }}" aria-valuenow="{{ $receivable->progressPercent() }}"
                         aria-valuemin="0" aria-valuemax="100">
                        <div class="progress-bar bg-success" style="width: {{ $receivable->progressPercent() }}%"></div>
                    </div>
                </div></div>
            @empty
                <div class="card border-0"><div class="card-body text-center py-5">
                    <i class="bi bi-emoji-smile fs-1 text-muted"></i>
                    <p class="mb-1 mt-2 fw-semibold">Nadie te debe nada</p>
                    <p class="text-muted small mb-3">
                        Si le prestaste plata a alguien o facturaste un trabajo, regístralo aquí para no perderle el rastro.
                    </p>
                    <a href="{{ route('receivables.create') }}" class="btn btn-finlia">
                        <i class="bi bi-plus-lg me-1"></i> Registrar cobro
                    </a>
                </div></div>
            @endforelse

            {{-- Historial de saldadas --}}
            @if ($settled->isNotEmpty())
                <h2 class="h6 text-muted text-uppercase mt-4 mb-2">Ya cerradas</h2>
                @foreach ($settled as $receivable)
                    <div class="card border-0 mb-2"><div class="card-body py-2">
                        <div class="d-flex justify-content-between align-items-center gap-2">
                            <a href="{{ route('receivables.show', $receivable) }}" class="text-decoration-none text-body">
                                <i class="bi bi-person-check me-1"></i>{{ $receivable->debtor_name }} · {{ $receivable->name }}
                            </a>
                            <span class="badge {{ $receivable->status->badgeClass() }}">{{ $receivable->status->label() }}</span>
                        </div>
                    </div></div>
                @endforeach
            @endif
        </div>
    </div>

@endsection
