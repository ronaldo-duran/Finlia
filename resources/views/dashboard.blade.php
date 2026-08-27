@extends('layouts.app', ['title' => 'Panel'])

@php
    $variant = design_variant();
    $isNegative = $budgetSummary['available'] < 0;
@endphp

@section('content')
    <x-flash-messages />

    {{-- Encabezado de bienvenida --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
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

    {{-- Alertas de presupuesto --}}
    @if ($budgetSummary['exceeded']->isNotEmpty())
        <div class="alert alert-danger d-flex gap-2" role="alert">
            <i class="bi bi-x-octagon-fill fs-5"></i>
            <div>
                <strong>Presupuesto excedido</strong> en
                {{ $budgetSummary['exceeded']->pluck('name')->join(', ', ' y ') }}.
                <a href="{{ route('budgets.index') }}" class="alert-link">Ver detalle</a>
            </div>
        </div>
    @endif
    @if ($budgetSummary['warnings']->isNotEmpty())
        <div class="alert alert-warning d-flex gap-2" role="alert">
            <i class="bi bi-exclamation-triangle-fill fs-5"></i>
            <div>
                Vas por encima del 80 % en
                {{ $budgetSummary['warnings']->pluck('name')->join(', ', ' y ') }}.
                <a href="{{ route('budgets.index') }}" class="alert-link">Ver detalle</a>
            </div>
        </div>
    @endif

    @if ($variant === 'b')
        @include('dashboard._hero-control', ['budgetSummary' => $budgetSummary, 'isNegative' => $isNegative])
    @else
        @include('dashboard._hero-enfoque', ['budgetSummary' => $budgetSummary, 'isNegative' => $isNegative])
    @endif

    {{-- KPIs del mes --}}
    <div class="d-flex justify-content-between align-items-center mb-2">
        <span class="small fw-semibold text-muted text-uppercase">Resumen del mes</span>
        <a href="{{ route('budgets.index') }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-cash-stack me-1"></i> Ver presupuestos
        </a>
    </div>
    @php
        $kpis = [
            ['label' => 'Ingresos del mes', 'value' => $totals['incomes'], 'icon' => 'bi-graph-up-arrow', 'tone' => 'finlia'],
            ['label' => 'Gastos del mes', 'value' => $totals['expenses'], 'icon' => 'bi-graph-down-arrow', 'tone' => 'finlia'],
            ['label' => 'Balance del mes', 'value' => $totals['balance'], 'icon' => 'bi-scale', 'tone' => 'finlia'],
            ['label' => 'Saldo en cuentas', 'value' => $totalBalance, 'icon' => 'bi-wallet2', 'tone' => 'finlia'],
        ];
    @endphp
    <div class="row g-2 g-md-3 mb-4">
        @foreach ($kpis as $kpi)
            <div class="col-6 col-xl-3">
                <div class="card h-100 border-0">
                    {{-- El icono se oculta bajo sm: a 375 px los 48 px del avatar
                         dejaban sin sitio al importe, que quedaba truncado. --}}
                    <div class="card-body p-3 d-flex align-items-center gap-2 gap-md-3">
                        <div class="d-none d-sm-flex rounded-3 bg-{{ $kpi['tone'] }}-subtle text-{{ $kpi['tone'] }} align-items-center justify-content-center flex-shrink-0"
                             style="width: 48px; height: 48px;">
                            <i class="bi {{ $kpi['icon'] }} fs-4"></i>
                        </div>
                        <div class="min-w-0">
                            <div class="text-muted small text-uppercase">
                                <i class="bi {{ $kpi['icon'] }} me-1 d-sm-none text-finlia"></i>{{ $kpi['label'] }}
                            </div>
                            {{-- money-figure en vez de text-truncate: truncar un
                                 importe ocultaría dígitos. --}}
                            <div class="fw-bold money-figure">@money($kpi['value'])</div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    @if ($variant === 'b' && $budgetSummary['categories']->isNotEmpty())
        {{-- Presupuesto por categoría: la variante "Control" mantiene el detalle a la vista. --}}
        <div class="card border-0 mb-4">
            <div class="card-header border-0 bg-transparent d-flex justify-content-between align-items-center fw-semibold">
                <span><i class="bi bi-cash-stack me-1"></i> Presupuesto por categoría</span>
                <a href="{{ route('budgets.index') }}" class="btn btn-sm btn-outline-secondary">Ajustar</a>
            </div>
            <div class="card-body d-flex flex-column gap-3">
                @foreach ($budgetSummary['categories'] as $row)
                    <div>
                        <div class="d-flex justify-content-between small fw-semibold mb-1">
                            <span>{{ $row['name'] }}</span>
                            <span class="text-{{ $row['level']->color() === 'success' ? 'muted' : $row['level']->color() }} budget-figures">
                                @money($row['spent']) / @money($row['budget'])
                            </span>
                        </div>
                        <div class="progress" role="progressbar" aria-label="Consumo de {{ $row['name'] }}"
                             aria-valuenow="{{ min(100, $row['percent']) }}" aria-valuemin="0" aria-valuemax="100" style="height: 6px;">
                            <div class="progress-bar bg-{{ $row['level']->color() }}" style="width: {{ min(100, $row['percent']) }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Gráficos --}}
    <div class="row g-3 mb-4">
        {{-- Tendencia ingresos vs gastos (6 meses) --}}
        <div class="col-12 col-lg-7">
            <div class="card border-0 h-100">
                <div class="card-header border-0 bg-transparent fw-semibold">
                    <i class="bi bi-bar-chart me-1"></i> Ingresos vs gastos (6 meses)
                </div>
                <div class="card-body">
                    <canvas id="trendChart" height="140"></canvas>
                </div>
            </div>
        </div>

        {{-- Gastos por categoría (mes actual) --}}
        <div class="col-12 col-lg-5">
            <div class="card border-0 h-100">
                <div class="card-header border-0 bg-transparent fw-semibold">
                    <i class="bi bi-pie-chart me-1"></i> Gastos por categoría (mes)
                </div>
                <div class="card-body">
                    @if ($byCategory->isEmpty())
                        <div class="text-center text-muted py-4">
                            <i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>
                            Aún no has registrado gastos este mes.
                        </div>
                    @else
                        <canvas id="categoryChart" height="180"></canvas>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Últimos movimientos --}}
    <div class="card border-0">
        <div class="card-header border-0 bg-transparent d-flex justify-content-between align-items-center fw-semibold">
            <span><i class="bi bi-clock-history me-1"></i> Últimos movimientos</span>
            <a href="{{ route('movements.index') }}" class="btn btn-sm btn-outline-secondary">Ver todos</a>
        </div>
        @if ($recent->isEmpty())
            <div class="card-body text-center text-muted py-4">
                <i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>
                Todavía no hay movimientos. Empieza con <a href="{{ route('expenses.create') }}">“Registrar gasto”</a>.
            </div>
        @else
            <div class="list-group list-group-flush">
                @foreach ($recent as $m)
                    @include('movements._item', ['m' => $m])
                @endforeach
            </div>
        @endif
    </div>

    {{-- Datos para Chart.js (leídos por resources/js/charts.js) --}}
    <script type="application/json" id="finlia-chart-data">{{ json_encode($chartData) }}</script>
    @vite('resources/js/charts.js')
@endsection
