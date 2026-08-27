@extends('layouts.app', ['title' => 'Presupuestos'])

@section('content')
    <x-flash-messages />

    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div class="min-w-0">
            <h1 class="h3 mb-1"><i class="bi bi-cash-stack me-2"></i>Presupuestos</h1>
            <p class="text-muted mb-0 small">
                <i class="bi bi-calendar3 me-1"></i>
                {{ $summary['from']->format('d/m/Y') }} – {{ $summary['to']->format('d/m/Y') }}
                @if ($summary['prorated'])
                    · <span class="text-body-secondary">presupuesto mensual prorrateado</span>
                @endif
            </p>
        </div>
        {{-- Ancho completo en móvil, ajustado al contenido desde sm --}}
        <a href="{{ route('budgets.create', ['periodo' => $scope->value]) }}"
           class="btn btn-finlia w-100 w-sm-auto">
            <i class="bi bi-plus-circle me-1"></i> Nuevo presupuesto
        </a>
    </div>

    {{-- Selector de período: esta semana / este mes / próximo mes. --}}
    <div class="chip-row mb-4" role="group" aria-label="Período consultado">
        @foreach (\App\Enums\BudgetScope::cases() as $option)
            <a href="{{ route('budgets.index', ['periodo' => $option->value]) }}"
               class="chip {{ $scope === $option ? 'active' : '' }}"
               @if ($scope === $option) aria-current="page" @endif>
                {{ $option->label() }}
            </a>
        @endforeach
    </div>

    {{-- Alertas de categorías al 80 % / 100 % --}}
    @if ($summary['exceeded']->isNotEmpty())
        <div class="alert alert-danger d-flex gap-2" role="alert">
            <i class="bi bi-x-octagon-fill fs-5"></i>
            <div>
                <strong>Presupuesto excedido</strong> en
                {{ $summary['exceeded']->pluck('name')->join(', ', ' y ') }}.
                Revisa esos gastos antes de seguir.
            </div>
        </div>
    @endif
    @if ($summary['warnings']->isNotEmpty())
        <div class="alert alert-warning d-flex gap-2" role="alert">
            <i class="bi bi-exclamation-triangle-fill fs-5"></i>
            <div>
                Vas por encima del 80 % en
                {{ $summary['warnings']->pluck('name')->join(', ', ' y ') }}.
            </div>
        </div>
    @endif

    {{-- Tarjeta principal + desglose --}}
    <div class="row g-3 mb-4">
        <div class="col-12 col-lg-5">
            <x-available-money-card :summary="$summary" />
        </div>

        <div class="col-12 col-lg-7">
            @php
                $kpis = [
                    ['label' => 'Gastado', 'value' => $summary['spent'], 'icon' => 'bi-graph-down-arrow', 'tone' => 'danger'],
                    ['label' => 'Comprometido', 'value' => $summary['committed']['total'], 'icon' => 'bi-lock', 'tone' => 'warning'],
                    ['label' => 'Libre en cuentas', 'value' => $summary['free'], 'icon' => 'bi-unlock', 'tone' => 'success'],
                ];
            @endphp
            <div class="row g-2 g-md-3">
                @foreach ($kpis as $kpi)
                    <div class="col-6 col-xxl-3">
                        <div class="card h-100 border-0">
                            <div class="card-body p-3">
                                <div class="text-muted small text-uppercase">
                                    <i class="bi {{ $kpi['icon'] }} me-1 text-{{ $kpi['tone'] }}"></i>{{ $kpi['label'] }}
                                </div>
                                {{-- money-figure en vez de text-truncate: truncar
                                     un importe ocultaría dígitos. --}}
                                <div class="fw-bold mt-2 money-figure">@money($kpi['value'])</div>
                            </div>
                        </div>
                    </div>
                @endforeach
                <div class="col-6 col-xxl-3">
                    <div class="card h-100 border-0">
                        <div class="card-body p-3">
                            <div class="text-muted small text-uppercase">
                                <i class="bi bi-hourglass-split me-1 text-primary"></i>Días restantes
                            </div>
                            <div class="fw-bold mt-2 money-figure">{{ $summary['days_remaining'] }}</div>
                            <div class="small text-muted">de {{ $summary['days_total'] }}</div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Explicación opcional: la fórmula no se impone, se ofrece --}}
            <div class="card border-0 mt-3">
                <div class="card-body py-2">
                    <a class="small text-decoration-none d-block" data-bs-toggle="collapse" href="#comoSeCalcula"
                       role="button" aria-expanded="false" aria-controls="comoSeCalcula">
                        <i class="bi bi-question-circle me-1"></i> ¿Cómo se calcula?
                    </a>
                    <div class="collapse mt-2" id="comoSeCalcula">
                        <ul class="list-unstyled small text-muted mb-0 budget-figures">
                            <li class="d-flex justify-content-between gap-2">
                                <span>Ingresos esperados</span>
                                <span class="fw-semibold text-success">@money($summary['expected_income'])</span>
                            </li>
                            <li class="d-flex justify-content-between gap-2">
                                <span>− Ya gastado</span>
                                <span class="fw-semibold text-danger">@money($summary['spent'])</span>
                            </li>
                            <li class="d-flex justify-content-between gap-2">
                                <span>− Presupuesto aún sin gastar</span>
                                <span class="fw-semibold">@money($summary['committed']['budget'])</span>
                            </li>
                            <li class="d-flex justify-content-between text-body-tertiary">
                                <span>− Gastos fijos y recurrentes</span>
                                <span>Épica 5</span>
                            </li>
                            <li class="d-flex justify-content-between text-body-tertiary">
                                <span>− Obligaciones de deuda</span>
                                <span>Épica 6</span>
                            </li>
                            <li class="d-flex justify-content-between text-body-tertiary">
                                <span>− Ahorro programado</span>
                                <span>Épica 7</span>
                            </li>
                            <li><hr class="my-2"></li>
                            <li class="d-flex justify-content-between gap-2">
                                <span class="fw-semibold text-body">= Puedes gastar</span>
                                <span class="fw-bold text-body">@money($summary['available'])</span>
                            </li>
                            <li class="d-flex justify-content-between mt-2 pt-2 border-top">
                                <span>Saldo actual en cuentas</span>
                                <span class="fw-semibold">@money($summary['current_balance'])</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Consumo global del presupuesto + tendencia --}}
    @if ($summary['has_budget'])
        @php
            $level = $summary['level'];
            $percent = $summary['consumed_percent'];
        @endphp
        <div class="card border-0 mb-4">
            <div class="card-body">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                    <span class="fw-semibold"><i class="bi bi-speedometer2 me-1"></i> Presupuesto consumido</span>
                    <span class="badge rounded-pill text-bg-{{ $level->color() }}">
                        <i class="bi {{ $level->icon() }} me-1"></i>@percent($percent) · {{ $level->label() }}
                    </span>
                </div>
                <div class="progress" role="progressbar" aria-label="Presupuesto consumido"
                     aria-valuenow="{{ min(100, $percent) }}" aria-valuemin="0" aria-valuemax="100" style="height: 12px;">
                    <div class="progress-bar bg-{{ $level->color() }}" style="width: {{ min(100, $percent) }}%"></div>
                </div>
                <div class="d-flex flex-wrap justify-content-between gap-1 small text-muted mt-2 budget-figures">
                    <span>@money($summary['spent']) gastado</span>
                    <span>@money($summary['budget_defined']) presupuestado</span>
                </div>

                @if ($summary['trend'])
                    <p class="small mb-0 mt-3">
                        <i class="bi bi-graph-up me-1"></i>
                        @switch($summary['trend'])
                            @case('over')
                                A este ritmo terminarás gastando <strong class="text-danger">@money($summary['projected_spend'])</strong>,
                                por encima de lo presupuestado.
                                @break
                            @case('under')
                                A este ritmo terminarás gastando <strong class="text-success">@money($summary['projected_spend'])</strong>,
                                por debajo de lo presupuestado. Buen trabajo.
                                @break
                            @default
                                A este ritmo terminarás gastando <strong>@money($summary['projected_spend'])</strong>,
                                en línea con lo presupuestado.
                        @endswitch
                    </p>
                @endif
            </div>
        </div>
    @endif

    {{-- Detalle por categoría --}}
    <div class="card border-0 mb-4">
        <div class="card-header border-0 bg-transparent d-flex justify-content-between align-items-center fw-semibold">
            <span><i class="bi bi-list-check me-1"></i> Por categoría · {{ $monthLabel }}</span>
            @if ($summary['prorated'])
                <span class="badge rounded-pill text-bg-light text-muted">Prorrateado a la semana</span>
            @endif
        </div>

        @if ($summary['categories']->isEmpty())
            <div class="card-body text-center text-muted py-5">
                <i class="bi bi-clipboard-x fs-1 d-block mb-2 opacity-50"></i>
                <p class="mb-3">Todavía no has presupuestado ninguna categoría para {{ $monthLabel }}.</p>
                <a href="{{ route('budgets.create', ['periodo' => $scope->value]) }}" class="btn btn-finlia">
                    <i class="bi bi-plus-circle me-1"></i> Crear el primero
                </a>
            </div>
        @else
            <div class="list-group list-group-flush">
                @foreach ($summary['categories'] as $row)
                    <div class="list-group-item">
                        {{-- Móvil: nombre arriba, importes debajo. Desde md, en la
                             misma línea. Así el par "gastado / presupuestado" nunca
                             se comprime ni se corta. --}}
                        <div class="d-md-flex justify-content-between align-items-center gap-2 mb-2">
                            <div class="d-flex align-items-center flex-wrap gap-2 min-w-0">
                                <span class="color-dot" style="background-color: {{ $row['color'] ?: '#0f6f66' }}"></span>
                                <span class="fw-semibold text-truncate">{{ $row['name'] }}</span>
                                @if ($row['level'] !== \App\Enums\BudgetAlertLevel::Ok)
                                    <span class="badge rounded-pill text-bg-{{ $row['level']->color() }}">
                                        <i class="bi {{ $row['level']->icon() }} me-1"></i>{{ $row['level']->label() }}
                                    </span>
                                @endif
                            </div>
                            <span class="small text-muted budget-figures d-block text-md-end mt-1 mt-md-0">
                                @money($row['spent']) / @money($row['budget'])
                            </span>
                        </div>

                        <div class="progress" role="progressbar" aria-label="Consumo de {{ $row['name'] }}"
                             aria-valuenow="{{ min(100, $row['percent']) }}" aria-valuemin="0" aria-valuemax="100"
                             style="height: 8px;">
                            <div class="progress-bar bg-{{ $row['level']->color() }}"
                                 style="width: {{ min(100, $row['percent']) }}%"></div>
                        </div>

                        <div class="small text-muted mt-1">
                            @percent($row['percent'])
                            @if ($row['overspent'] > 0)
                                · <span class="text-danger">@money($row['overspent']) por encima</span>
                            @else
                                · quedan @money($row['remaining'])
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Presupuestos definidos para el mes (editar / eliminar) --}}
    @if ($budgets->isNotEmpty())
        <div class="card border-0">
            <div class="card-header border-0 bg-transparent fw-semibold">
                <i class="bi bi-sliders me-1"></i> Presupuestos de {{ $monthLabel }}
            </div>
            <div class="list-group list-group-flush">
                @foreach ($budgets as $budget)
                    <div class="list-group-item d-flex justify-content-between align-items-center gap-2">
                        <div class="min-w-0">
                            <div class="fw-semibold text-truncate">
                                {{ $budget->isTotal() ? 'Presupuesto total del mes' : $budget->category?->name }}
                            </div>
                            <div class="small text-muted">
                                @money($budget->amount) · {{ $budget->period->label() }}
                            </div>
                        </div>
                        <div class="d-flex gap-1">
                            <a href="{{ route('budgets.edit', $budget) }}" class="btn btn-sm btn-icon" aria-label="Editar">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <form method="POST" action="{{ route('budgets.destroy', $budget) }}"
                                  data-confirm="¿Eliminar este presupuesto?">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-icon text-danger" aria-label="Eliminar">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
@endsection
