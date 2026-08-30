@extends('layouts.app', ['title' => 'Panel'])

@php
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

    {{-- Épica 5: obligaciones vencidas o próximas a vencer (in-app, ADR-0015) --}}
    @php
        $vencidas = $recurringAlerts->where('is_overdue');
        $proximas = $recurringAlerts->where('is_overdue', false);
    @endphp
    @if ($vencidas->isNotEmpty())
        <div class="alert alert-danger d-flex gap-2" role="alert">
            <i class="bi bi-exclamation-octagon-fill fs-5"></i>
            <div>
                <strong>Obligaciones vencidas</strong>:
                {{ $vencidas->map(fn ($r) => $r['name'].' ('.money($r['amount']).')')->join(', ', ' y ') }}.
                <a href="{{ route('recurring-expenses.index') }}" class="alert-link">Regularizar</a>
            </div>
        </div>
    @endif
    @if ($proximas->isNotEmpty())
        <div class="alert alert-warning d-flex gap-2" role="alert">
            <i class="bi bi-bell-fill fs-5"></i>
            <div>
                {{ $proximas->map(fn ($r) => $r['name'].' vence en '.$r['days_remaining'].' día'.($r['days_remaining'] === 1 ? '' : 's'))->join('; ', ' y ') }}.
                <a href="{{ route('recurring-expenses.index') }}" class="alert-link">Ver obligaciones</a>
            </div>
        </div>
    @endif

    {{-- Épica 9: resumen de TODAS las fuentes (recurrentes, deudas, metas y
         sueltos). Es navegación hacia /recordatorios, no una alarma más:
         borde y tinte de marca, discreto. --}}
    @if ($reminderSummary !== null && $reminderSummary['attention'] > 0)
        <div class="alert d-flex gap-2 align-items-center mb-3 border border-finlia bg-finlia-subtle text-finlia" role="status">
            <i class="bi bi-bell-fill fs-5"></i>
            <div>
                🔔 Tienes
                <strong>{{ $reminderSummary['attention'] }} {{ $reminderSummary['attention'] === 1 ? 'obligación próxima' : 'obligaciones próximas' }}</strong>
                @if ($reminderSummary['overdue'] > 0)
                    ({{ $reminderSummary['overdue'] }} {{ $reminderSummary['overdue'] === 1 ? 'vencida' : 'vencidas' }})
                @endif.
                <a href="{{ route('reminders.index') }}" class="alert-link">Ver recordatorios</a>
            </div>
        </div>
    @endif

    @include('dashboard._hero-enfoque', ['budgetSummary' => $budgetSummary, 'isNegative' => $isNegative])

    {{-- KPIs del mes (Épica 8: el resumen completo — deuda y ahorro incluidos) --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
        <span class="small fw-semibold text-muted text-uppercase">Resumen del mes</span>
        <div class="d-flex gap-2">
            <a href="{{ route('reports.index') }}" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-bar-chart-line me-1"></i> Ver reportes
            </a>
            <a href="{{ route('budgets.index') }}" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-cash-stack me-1"></i> Ver presupuestos
            </a>
        </div>
    </div>
    @php
        $kpis = [
            ['label' => 'Ingresos del mes', 'value' => $totals['incomes'], 'icon' => 'bi-graph-up-arrow', 'tone' => 'finlia'],
            ['label' => 'Gastos del mes', 'value' => $totals['expenses'], 'icon' => 'bi-graph-down-arrow', 'tone' => 'finlia'],
            ['label' => 'Balance del mes', 'value' => $totals['balance'], 'icon' => 'bi-plus-slash-minus', 'tone' => 'finlia'],
            ['label' => 'Saldo en cuentas', 'value' => $totalBalance, 'icon' => 'bi-wallet2', 'tone' => 'finlia'],
            ['label' => 'Deuda total', 'value' => $debtSummary['total_balance'], 'icon' => 'bi-credit-card-2-front', 'tone' => 'finlia'],
            ['label' => 'Ahorro en metas', 'value' => $savingsSummary['total_saved'], 'icon' => 'bi-piggy-bank', 'tone' => 'finlia'],
        ];
    @endphp
    <div class="row g-2 g-md-3 mb-4">
        @foreach ($kpis as $kpi)
            <div class="col-6 col-xl-4">
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

    {{-- Metas de ahorro: progreso visual (Épica 7) --}}
    @if ($savingsGoals->isNotEmpty())
        <div class="card border-0 mb-4">
            <div class="card-header border-0 bg-transparent d-flex justify-content-between align-items-center fw-semibold">
                <span><i class="bi bi-piggy-bank me-1"></i> Metas de ahorro</span>
                <a href="{{ route('savings-goals.index') }}" class="btn btn-sm btn-outline-secondary">Ver todas</a>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    @foreach ($savingsGoals->take(3) as $goal)
                        <div class="col-12 col-md-4">
                            <div class="small fw-semibold text-truncate">
                                <i class="bi {{ $goal->is_emergency_fund ? 'bi-shield-check' : 'bi-flag' }} me-1"></i>{{ $goal->name }}
                            </div>
                            <div class="progress mt-1" style="height:.4rem" role="progressbar"
                                 aria-label="Progreso de {{ $goal->name }}" aria-valuenow="{{ $goal->progressPercent() }}"
                                 aria-valuemin="0" aria-valuemax="100">
                                <div class="progress-bar bg-success" style="width: {{ $goal->progressPercent() }}%"></div>
                            </div>
                            <div class="small text-muted mt-1">
                                @money($goal->current_amount) de @money($goal->target_amount)
                                ({{ str_replace('.', ',', (string) $goal->progressPercent()) }} %)
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

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

    {{-- Datos para Chart.js (leídos por resources/js/charts.js).
         JSON_HEX_TAG: sin él, un "</script>" en un nombre de categoría
         cerraría este bloque; y el {{ }} de Blade escapa a &quot;, que
         JSON.parse no puede leer dentro de <script> (el navegador no
         decodifica entidades ahí): los gráficos quedaban en blanco. --}}
    <script type="application/json" id="finlia-chart-data">{!! json_encode($chartData, JSON_HEX_TAG) !!}</script>
    @vite('resources/js/charts.js')
@endsection
