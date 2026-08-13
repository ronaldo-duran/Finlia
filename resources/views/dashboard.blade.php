@extends('layouts.app', ['title' => 'Panel'])

@section('content')
    <x-flash-messages />

    {{-- Encabezado de bienvenida --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <div>
            <h1 class="h3 mb-1">Hola, {{ $user->name }} 👋</h1>
            <p class="text-muted mb-0">
                <i class="bi bi-calendar3 me-1"></i> {{ ucfirst($fechaActual) }}
            </p>
        </div>
        <a href="{{ active_household() ? route('households.show', active_household()) : route('households.create') }}"
           class="badge bg-finlia-subtle text-finlia border border-finlia rounded-pill px-3 py-2 text-decoration-none">
            <i class="bi bi-people-fill me-1"></i>
            {{ active_household()?->name ?? 'Crear hogar' }}
        </a>
    </div>

    {{-- Estado inicial: las tarjetas se llenarán en próximas épicas --}}
    <div class="alert alert-info d-flex align-items-start gap-2 border-0 shadow-sm" role="alert">
        <i class="bi bi-info-circle-fill mt-1"></i>
        <div>
            <strong>¡Tu panel está listo!</strong> Aquí verás pronto tu dinero disponible, ingresos, gastos,
            deudas y metas de ahorro. Completa la configuración de tu hogar en las próximas épicas para empezar.
        </div>
    </div>

    {{-- KPIs vacíos (placeholders) --}}
    <div class="row g-3 mb-4">
        @php
            $kpis = [
                ['label' => 'Disponible para gastar', 'value' => '—', 'icon' => 'bi-wallet2', 'epic' => 'Épica 4'],
                ['label' => 'Ingresos del mes', 'value' => '—', 'icon' => 'bi-graph-up-arrow', 'epic' => 'Épica 3'],
                ['label' => 'Gastos del mes', 'value' => '—', 'icon' => 'bi-graph-down-arrow', 'epic' => 'Épica 3'],
                ['label' => 'Deuda total', 'value' => '—', 'icon' => 'bi-credit-card', 'epic' => 'Épica 6'],
            ];
        @endphp
        @foreach ($kpis as $kpi)
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card h-100 border-0">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="rounded-3 bg-finlia-subtle text-finlia d-flex align-items-center justify-content-center"
                             style="width: 48px; height: 48px;">
                            <i class="bi {{ $kpi['icon'] }} fs-4"></i>
                        </div>
                        <div>
                            <div class="text-muted small text-uppercase">{{ $kpi['label'] }}</div>
                            <div class="fs-4 fw-bold lh-1">{{ $kpi['value'] }}</div>
                            <span class="badge text-bg-light text-muted">{{ $kpi['epic'] }}</span>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Sección de gráficos (placeholder) --}}
    <div class="row g-3">
        <div class="col-12 col-lg-8">
            <div class="card border-0 h-100">
                <div class="card-header border-0 bg-transparent fw-semibold">
                    <i class="bi bi-bar-chart me-1"></i> Resumen del mes
                </div>
                <div class="card-body text-center text-muted py-5">
                    <i class="bi bi-bar-chart-line fs-1 d-block mb-2 opacity-50"></i>
                    Los gráficos aparecerán aquí (Chart.js) a partir de la Épica 8.
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-4">
            <div class="card border-0 h-100">
                <div class="card-header border-0 bg-transparent fw-semibold">
                    <i class="bi bi-list-check me-1"></i> Próximas obligaciones
                </div>
                <div class="card-body text-center text-muted py-5">
                    <i class="bi bi-calendar2-check fs-1 d-block mb-2 opacity-50"></i>
                    Sin obligaciones próximas todavía (Épica 5).
                </div>
            </div>
        </div>
    </div>
@endsection
