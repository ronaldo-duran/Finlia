@extends('layouts.app', ['title' => 'Reportes'])

@php
    // El aumento es bueno para ingresos/balance y malo para gastos: el tono
    // del delta depende de la métrica, no del signo.
    $metrics = [
        ['label' => 'Ingresos', 'current' => $overview['incomes'], 'previous' => $overview['previous']['incomes'], 'delta' => $overview['deltas']['incomes'], 'goodWhenUp' => true],
        ['label' => 'Gastos', 'current' => $overview['expenses'], 'previous' => $overview['previous']['expenses'], 'delta' => $overview['deltas']['expenses'], 'goodWhenUp' => false],
        ['label' => 'Balance', 'current' => $overview['balance'], 'previous' => $overview['previous']['balance'], 'delta' => $overview['deltas']['balance'], 'goodWhenUp' => true],
    ];
@endphp

@section('content')
    <x-flash-messages />

    {{-- Encabezado --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div>
            <h1 class="h3 mb-1">Reportes</h1>
            <p class="text-muted mb-0">
                <i class="bi bi-people-fill me-1"></i> {{ $household->name }}
            </p>
        </div>
        {{-- PDF queda preparado (ReportFormat), hoy exporta CSV --}}
        <a href="{{ route('reports.export', ['period' => $period->value]) }}"
           class="btn btn-outline-finlia text-decoration-none">
            <i class="bi bi-download me-1"></i> Exportar CSV
        </a>
    </div>

    {{-- Comparación de períodos (Épica 8): el chip fija el ?period= real --}}
    <div class="chip-row mb-3" role="navigation" aria-label="Período del reporte">
        @foreach (App\Enums\ReportPeriod::cases() as $option)
            <a href="{{ route('reports.index', ['period' => $option->value]) }}"
               class="chip {{ $option === $period ? 'active' : '' }}"
               @if ($option === $period) aria-current="true" @endif>
                {{ $option->label() }}
            </a>
        @endforeach
    </div>

    {{-- Resumen comparativo --}}
    <div class="card border-0 mb-3">
        <div class="card-header border-0 bg-transparent fw-semibold">
            <i class="bi bi-bar-chart-steps me-1"></i> Resumen · {{ $overview['label'] }}
        </div>
        <div class="card-body pt-0">
            <div class="text-muted small mb-2">
                Comparado con {{ $overview['previous_label'] }}
            </div>
            @foreach ($metrics as $metric)
                @php
                    $up = $metric['delta']['absolute'] > 0;
                    $down = $metric['delta']['absolute'] < 0;
                    $good = $metric['goodWhenUp'] ? $up : $down;
                    $bad = $metric['goodWhenUp'] ? $down : $up;
                @endphp
                <div class="d-flex justify-content-between align-items-baseline gap-3 py-2
                            {{ ! $loop->last ? 'border-bottom' : '' }}">
                    <span class="fw-semibold">{{ $metric['label'] }}</span>
                    <span class="text-end">
                        <span class="money-figure fw-bold">@money($metric['current'])</span>
                        @if ($metric['delta']['percent'] !== null && ($up || $down))
                            <span class="small d-block mt-1 {{ $good ? 'text-success' : ($bad ? 'text-danger' : 'text-muted') }}">
                                <i class="bi {{ $up ? 'bi-arrow-up-right' : 'bi-arrow-down-right' }} me-1"></i>
                                {{ str_replace('.', ',', (string) abs($metric['delta']['percent'])) }} %
                                vs {{ $overview['previous_label'] }}
                            </span>
                        @endif
                    </span>
                </div>
            @endforeach

            {{-- Deuda y ahorro: punto en el tiempo, no del período --}}
            <div class="row g-2 g-md-3 mt-2">
                <div class="col-6">
                    <div class="h-100 p-3 rounded-3 bg-finlia-subtle">
                        <div class="text-muted small text-uppercase">Deuda total</div>
                        <div class="fw-bold money-figure">@money($overview['debt']['total_balance'])</div>
                        <a href="{{ route('debts.index') }}" class="small text-decoration-none">
                            {{ $overview['debt']['count'] }} deuda{{ $overview['debt']['count'] === 1 ? '' : 's' }} · ver detalle
                        </a>
                    </div>
                </div>
                <div class="col-6">
                    <div class="h-100 p-3 rounded-3 bg-finlia-subtle">
                        <div class="text-muted small text-uppercase">Ahorro en metas</div>
                        <div class="fw-bold money-figure">@money($overview['savings']['total_saved'])</div>
                        <a href="{{ route('savings-goals.index') }}" class="small text-decoration-none">
                            de @money($overview['savings']['total_target']) · ver metas
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Insights (Épica 8): hechos descriptivos, nunca consejos financieros --}}
    <div class="card border-0 mb-3">
        <div class="card-header border-0 bg-transparent fw-semibold">
            <i class="bi bi-lightbulb me-1"></i> Observaciones
        </div>
        <div class="card-body pt-0">
            @if ($insights->isEmpty())
                <div class="text-center text-muted py-3">
                    <i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>
                    Aún no hay suficientes datos para generar observaciones de este período.
                </div>
            @else
                <ul class="list-unstyled mb-0 d-grid gap-2">
                    @foreach ($insights as $insight)
                        <li class="d-flex align-items-start gap-2">
                            <span class="d-inline-flex align-items-center justify-content-center rounded-3 flex-shrink-0
                                         bg-{{ $insight['tone'] }}-subtle text-{{ $insight['tone'] }}"
                                  style="width: 36px; height: 36px;">
                                <i class="bi {{ $insight['icon'] }}"></i>
                            </span>
                            <span class="pt-1">{{ $insight['text'] }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

    {{-- Gráficos: apilados en móvil (un gráfico por fila, Épica 8 mobile) --}}
    <div class="row g-3 mb-4">
        {{-- 1. Gastos por categoría --}}
        <div class="col-12 col-lg-5">
            <div class="card border-0 h-100">
                <div class="card-header border-0 bg-transparent fw-semibold">
                    <i class="bi bi-pie-chart me-1"></i> Gastos por categoría
                </div>
                <div class="card-body">
                    @if ($byCategory->isEmpty())
                        <div class="text-center text-muted py-4">
                            <i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>
                            Sin gastos registrados en {{ $overview['label'] }}.
                        </div>
                    @else
                        <canvas id="reportCategoryChart" height="180"></canvas>
                    @endif
                </div>
            </div>
        </div>

        {{-- 2. Ingresos vs gastos por mes --}}
        <div class="col-12 col-lg-7">
            <div class="card border-0 h-100">
                <div class="card-header border-0 bg-transparent fw-semibold">
                    <i class="bi bi-bar-chart me-1"></i> Ingresos vs gastos
                </div>
                <div class="card-body">
                    @if (empty($chartData['reportTrend']['labels']))
                        <div class="text-center text-muted py-4">
                            <i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>
                            Sin movimientos en el período.
                        </div>
                    @else
                        <canvas id="reportTrendChart" height="140"></canvas>
                    @endif
                </div>
            </div>
        </div>

        {{-- 3. Evolución mensual del balance --}}
        <div class="col-12 col-lg-6">
            <div class="card border-0 h-100">
                <div class="card-header border-0 bg-transparent fw-semibold">
                    <i class="bi bi-graph-up me-1"></i> Evolución mensual (balance)
                </div>
                <div class="card-body">
                    @if (empty($chartData['reportBalance']['labels']))
                        <div class="text-center text-muted py-4">
                            <i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>
                            Sin movimientos en el período.
                        </div>
                    @else
                        <canvas id="reportBalanceChart" height="140"></canvas>
                    @endif
                </div>
            </div>
        </div>

        {{-- 4. Evolución de deuda: serie de cierre de mes, últimos 6 meses --}}
        <div class="col-12 col-lg-6">
            <div class="card border-0 h-100">
                <div class="card-header border-0 bg-transparent fw-semibold">
                    <i class="bi bi-credit-card-2-front me-1"></i> Evolución de deuda (6 meses)
                </div>
                <div class="card-body">
                    @if ($debtEvolution === [] || max($chartData['reportDebt']['balances']) <= 0)
                        <div class="text-center text-muted py-4">
                            <i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>
                            Sin deudas registradas.
                        </div>
                    @else
                        <canvas id="reportDebtChart" height="140"></canvas>
                    @endif
                </div>
            </div>
        </div>

        {{-- 5. Progreso de metas --}}
        <div class="col-12">
            <div class="card border-0">
                <div class="card-header border-0 bg-transparent d-flex justify-content-between align-items-center fw-semibold">
                    <span><i class="bi bi-piggy-bank me-1"></i> Progreso de metas</span>
                    <a href="{{ route('savings-goals.index') }}" class="btn btn-sm btn-outline-secondary">Ver metas</a>
                </div>
                <div class="card-body">
                    @if ($goals->isEmpty())
                        <div class="text-center text-muted py-4">
                            <i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>
                            No hay metas de ahorro vigentes.
                        </div>
                    @else
                        <canvas id="reportGoalsChart" height="{{ max(120, 40 * $goals->count()) }}"></canvas>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Datos para Chart.js (leídos por resources/js/charts.js).
         JSON_HEX_TAG: sin él, un "</script>" en un nombre de categoría
         cerraría este bloque; y el {{ }} de Blade escapa a &quot;, que
         JSON.parse no puede leer dentro de <script> (el navegador no
         decodifica entidades ahí). --}}
    <script type="application/json" id="finlia-chart-data">{!! json_encode($chartData, JSON_HEX_TAG) !!}</script>
    @vite('resources/js/charts.js')
@endsection
