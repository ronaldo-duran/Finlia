@props([
    'summary',
    'compact' => false,
])

@php
    /**
     * Tarjeta principal de la Épica 4: "¿cuánto puedo gastar?".
     * Recibe el array de BudgetCalculatorService::summary().
     *
     * Semana y mes muestran la liquidez de hoy (ADR-0040): saldo real hasta
     * el próximo cobro. "Próximo mes" muestra el plan, que es una proyección
     * y no plata disponible.
     */
    $liquidity = $summary['liquidity'];
    $scope = $summary['scope'];

    if ($liquidity !== null) {
        $isNegative = $liquidity['status'] !== 'ok';
        $amount = $isNegative ? $liquidity['shortfall'] : $liquidity['daily_allowance'];
    } else {
        $isNegative = $summary['plan_available'] < 0;
        $amount = abs($summary['plan_available']);
    }
@endphp

{{-- Cobre = lo disponible (docs/BRAND.md). --}}
<div class="card border-0 h-100 {{ $isNegative ? 'bg-danger-subtle' : 'bg-finlia-accent-subtle' }}"
     data-testid="available-money">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-1 gap-sm-2 mb-1">
            <span class="text-uppercase small fw-semibold {{ $isNegative ? 'text-danger-emphasis' : 'text-finlia-accent' }}">
                💰
                @if ($liquidity === null)
                    {{ $isNegative ? 'Tu plan no cuadra' : 'Te quedaría según tu plan' }}
                @elseif ($liquidity['status'] === 'short')
                    Te falta plata antes de tu próximo pago
                @elseif ($liquidity['status'] === 'over_plan')
                    Te has pasado del plan
                @else
                    Puedes gastar hoy
                @endif
            </span>
            @unless ($compact)
                {{-- Redundante en móvil: el selector de período va justo encima. --}}
                <span class="badge rounded-pill text-bg-light text-muted text-nowrap d-none d-sm-inline-block">
                    {{ $scope->label() }}
                </span>
            @endunless
        </div>

        <div class="fw-bold mb-2 money-hero {{ $compact ? 'money-hero-compact' : '' }} {{ $isNegative ? 'text-danger-emphasis' : '' }}"
             data-testid="available-money-amount">
            @money($amount)
        </div>

        @if ($liquidity === null)
            <p class="{{ $isNegative ? 'text-danger-emphasis' : 'text-muted' }} small mb-0">
                {{-- Sin liquidez solo llega "próximo mes" (ver summary()). --}}
                @if ($isNegative)
                    Tus compromisos superan lo que esperas recibir el próximo mes.
                @else
                    Es lo que te sobraría el próximo mes con tus ingresos esperados y tus compromisos.
                    Una proyección, no plata disponible.
                @endif
            </p>
        @elseif ($liquidity['status'] === 'short')
            <p class="text-danger-emphasis small mb-0">
                Tu saldo no cubre los pagos que vencen antes del {{ $liquidity['payday']->format('d/m/Y') }}.
            </p>
        @elseif ($liquidity['status'] === 'over_plan')
            <p class="text-danger-emphasis small mb-0">
                Este mes ya gastaste más de lo que esperas recibir.
            </p>
        @else
            <p class="text-muted small mb-0">
                Son <strong>@money($liquidity['available'])</strong> en total.
                <x-payday-horizon :liquidity="$liquidity" />
                @if ($liquidity['limited_by'] === 'plan')
                    Tienes más en cuentas, pero tu plan del mes no da para más.
                @endif
            </p>
        @endif

        @if ($liquidity !== null)
            <x-liquidity-notes :liquidity="$liquidity" />
        @elseif (! $summary['has_expected_income'])
            <div class="mt-3 small">
                <i class="bi bi-info-circle me-1"></i>
                Aún no has configurado tus ingresos esperados.
                <a href="{{ route('expected-incomes.index') }}" class="fw-semibold">Configúralos</a>
                para proyectar el mes.
            </div>
        @endif
    </div>

    @if ($compact)
        <div class="card-footer border-0 bg-transparent pt-0">
            <a href="{{ route('budgets.index') }}" class="btn btn-sm btn-outline-finlia w-100">
                <i class="bi bi-cash-stack me-1"></i> Ver presupuestos
            </a>
        </div>
    @endif
</div>
