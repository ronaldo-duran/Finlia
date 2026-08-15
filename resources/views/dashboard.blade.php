@extends('layouts.app', ['title' => 'Panel'])

@section('content')
    <x-flash-messages />

    {{-- Encabezado de bienvenida + acciones rápidas --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <div>
            <h1 class="h3 mb-1">Hola, {{ $user->name }} 👋</h1>
            <p class="text-muted mb-0">
                <i class="bi bi-calendar3 me-1"></i> {{ ucfirst($fechaActual) }}
            </p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ active_household() ? route('households.show', active_household()) : route('households.create') }}"
               class="badge bg-finlia-subtle text-finlia border border-finlia rounded-pill px-3 py-2 text-decoration-none">
                <i class="bi bi-people-fill me-1"></i>
                {{ active_household()?->name ?? 'Crear hogar' }}
            </a>
        </div>
    </div>

    {{-- Botones de acción rápida --}}
    <div class="d-flex flex-wrap gap-2 mb-4">
        <a href="{{ route('expenses.create') }}" class="btn btn-finlia btn-lg">
            <i class="bi bi-dash-circle me-1"></i> Registrar gasto
        </a>
        <a href="{{ route('incomes.create') }}" class="btn btn-outline-finlia btn-lg">
            <i class="bi bi-plus-circle me-1"></i> Registrar ingreso
        </a>
        <a href="{{ route('movements.index') }}" class="btn btn-outline-secondary btn-lg">
            <i class="bi bi-list me-1"></i> Ver movimientos
        </a>
    </div>

    {{-- KPIs del mes --}}
    @php
        $kpis = [
            ['label' => 'Ingresos del mes', 'value' => $totals['incomes'], 'icon' => 'bi-graph-up-arrow', 'tone' => 'success'],
            ['label' => 'Gastos del mes', 'value' => $totals['expenses'], 'icon' => 'bi-graph-down-arrow', 'tone' => 'danger'],
            ['label' => 'Balance del mes', 'value' => $totals['balance'], 'icon' => 'bi-scale', 'tone' => $totals['balance'] >= 0 ? 'success' : 'danger'],
            ['label' => 'Saldo en cuentas', 'value' => $totalBalance, 'icon' => 'bi-wallet2', 'tone' => 'primary'],
        ];
    @endphp
    <div class="row g-3 mb-4">
        @foreach ($kpis as $kpi)
            <div class="col-6 col-xl-3">
                <div class="card h-100 border-0">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="rounded-3 bg-{{ $kpi['tone'] }}-subtle text-{{ $kpi['tone'] }}-emphasis d-flex align-items-center justify-content-center"
                             style="width: 48px; height: 48px;">
                            <i class="bi {{ $kpi['icon'] }} fs-4"></i>
                        </div>
                        <div class="min-w-0">
                            <div class="text-muted small text-uppercase text-truncate">{{ $kpi['label'] }}</div>
                            <div class="fs-5 fw-bold lh-1 text-truncate">@money($kpi['value'])</div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

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
                    @php
                        $isIncome = $m['type'] === 'income';
                        $icon = $isIncome ? 'bi-arrow-down-left-circle text-success' : 'bi-arrow-up-right-circle text-danger';
                    @endphp
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center gap-2 min-w-0">
                            <i class="bi {{ $icon }} fs-5"></i>
                            <div class="min-w-0">
                                <div class="fw-semibold text-truncate">
                                    {{ $m['description'] ?: ($isIncome ? 'Ingreso' : 'Gasto') }}
                                </div>
                                <div class="small text-muted text-truncate">
                                    {{ $m['category_name'] }}
                                    @if ($m['account_name']) · {{ $m['account_name'] }} @endif
                                    · {{ $m['date']->format('d/m/Y') }}
                                </div>
                            </div>
                        </div>
                        <span class="fw-bold {{ $isIncome ? 'text-success' : 'text-danger' }} text-nowrap">
                            {{ $isIncome ? '+' : '−' }}@money($m['amount'])
                        </span>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Datos para Chart.js (leídos por resources/js/charts.js) --}}
    <script type="application/json" id="finlia-chart-data">{{ json_encode($chartData) }}</script>
    @vite('resources/js/charts.js')
@endsection
