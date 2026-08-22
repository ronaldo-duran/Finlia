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

    {{-- Tarjeta principal: cuánto puedes gastar (Épica 4) + alertas de presupuesto --}}
    <div class="row g-3 mb-4">
        <div class="col-12 col-md-5 col-xl-4">
            <x-available-money-card :summary="$budgetSummary" compact />
        </div>
        <div class="col-12 col-md-7 col-xl-8">
            @if ($budgetSummary['exceeded']->isNotEmpty())
                <div class="alert alert-danger d-flex gap-2 mb-2" role="alert">
                    <i class="bi bi-x-octagon-fill fs-5"></i>
                    <div>
                        <strong>Presupuesto excedido</strong> en
                        {{ $budgetSummary['exceeded']->pluck('name')->join(', ', ' y ') }}.
                        <a href="{{ route('budgets.index') }}" class="alert-link">Ver detalle</a>
                    </div>
                </div>
            @endif
            @if ($budgetSummary['warnings']->isNotEmpty())
                <div class="alert alert-warning d-flex gap-2 mb-2" role="alert">
                    <i class="bi bi-exclamation-triangle-fill fs-5"></i>
                    <div>
                        Vas por encima del 80 % en
                        {{ $budgetSummary['warnings']->pluck('name')->join(', ', ' y ') }}.
                        <a href="{{ route('budgets.index') }}" class="alert-link">Ver detalle</a>
                    </div>
                </div>
            @endif

            @if ($budgetSummary['has_budget'])
                <div class="card border-0 h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="fw-semibold small text-uppercase text-muted">Presupuesto consumido</span>
                            <span class="badge rounded-pill text-bg-{{ $budgetSummary['level']->color() }}">
                                @percent($budgetSummary['consumed_percent'])
                            </span>
                        </div>
                        <div class="progress" role="progressbar" aria-label="Presupuesto consumido"
                             aria-valuenow="{{ min(100, $budgetSummary['consumed_percent']) }}"
                             aria-valuemin="0" aria-valuemax="100" style="height: 10px;">
                            <div class="progress-bar bg-{{ $budgetSummary['level']->color() }}"
                                 style="width: {{ min(100, $budgetSummary['consumed_percent']) }}%"></div>
                        </div>
                        <div class="d-flex flex-wrap justify-content-between gap-1 small text-muted mt-2 budget-figures">
                            <span>@money($budgetSummary['spent']) gastado</span>
                            <span>@money($budgetSummary['budget_defined']) presupuestado</span>
                        </div>
                    </div>
                </div>
            @elseif ($budgetSummary['exceeded']->isEmpty() && $budgetSummary['warnings']->isEmpty())
                <div class="card border-0 h-100">
                    <div class="card-body d-flex flex-column justify-content-center text-center text-muted">
                        <i class="bi bi-clipboard-check fs-2 mb-2 opacity-50"></i>
                        <p class="mb-3 small">Aún no tienes un presupuesto para este mes.</p>
                        <div>
                            <a href="{{ route('budgets.create') }}" class="btn btn-sm btn-outline-finlia">
                                <i class="bi bi-plus-circle me-1"></i> Crear presupuesto
                            </a>
                        </div>
                    </div>
                </div>
            @endif
        </div>
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
    <div class="row g-2 g-md-3 mb-4">
        @foreach ($kpis as $kpi)
            <div class="col-6 col-xl-3">
                <div class="card h-100 border-0">
                    {{-- El icono se oculta bajo sm: a 375 px los 48 px del avatar
                         dejaban sin sitio al importe, que quedaba truncado. --}}
                    <div class="card-body p-3 d-flex align-items-center gap-2 gap-md-3">
                        <div class="d-none d-sm-flex rounded-3 bg-{{ $kpi['tone'] }}-subtle text-{{ $kpi['tone'] }}-emphasis align-items-center justify-content-center flex-shrink-0"
                             style="width: 48px; height: 48px;">
                            <i class="bi {{ $kpi['icon'] }} fs-4"></i>
                        </div>
                        <div class="min-w-0">
                            <div class="text-muted small text-uppercase">
                                <i class="bi {{ $kpi['icon'] }} me-1 d-sm-none text-{{ $kpi['tone'] }}"></i>{{ $kpi['label'] }}
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
                                {{-- Sin text-truncate: en móvil ocultaba la fecha.
                                     Prefiere envolver a perder información. --}}
                                <div class="small text-muted">
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
