@php
    $liquidity = $budgetSummary['liquidity'];
    $heroAmount = $isNegative ? $liquidity['shortfall'] : $liquidity['daily_allowance'];
    $percent = $budgetSummary['consumed_percent'];
@endphp
<div class="hero-card {{ $isNegative ? 'bg-danger-subtle' : 'bg-finlia-accent-subtle' }} mb-3" data-testid="available-money">
    <div class="text-uppercase small fw-semibold {{ $isNegative ? 'text-danger' : 'text-finlia-accent' }} mb-2">
        <i class="bi bi-wallet2 me-1"></i>
        @switch($liquidity['status'])
            @case('short') Te falta plata antes de tu próximo pago @break
            @case('over_plan') Te has pasado del plan @break
            @default Puedes gastar hoy
        @endswitch
    </div>
    <div class="hero-figure" data-testid="available-money-amount">@money($heroAmount)</div>
    <x-liquidity-breakdown :liquidity="$liquidity" />
    <p class="text-muted small mb-0 mt-2" data-testid="available-money-horizon">
        @switch($liquidity['status'])
            @case('short')
                Cubre tus metas apartadas y los compromisos que vencen antes del {{ $liquidity['payday']->format('d/m/Y') }}.
                @break
            @case('over_plan')
                Este mes ya gastaste más de lo que esperas recibir.
                @break
            @default
                <x-payday-horizon :liquidity="$liquidity" />
                @if ($liquidity['limited_by'] === 'plan')
                    Tienes más en cuentas, pero tu plan del mes no da para más.
                @endif
        @endswitch
    </p>
    @if ($percent !== null)
        <div class="progress mt-3" role="progressbar" aria-label="Presupuesto consumido"
             aria-valuenow="{{ min(100, $percent) }}" aria-valuemin="0" aria-valuemax="100" style="height: 6px;">
            <div class="progress-bar bg-{{ $budgetSummary['level']->color() }}" style="width: {{ min(100, $percent) }}%"></div>
        </div>
        <div class="d-flex justify-content-between small text-muted mt-2">
            <span>@percent($percent) del presupuesto usado</span>
            <span class="budget-figures">@money($budgetSummary['budget_defined'])</span>
        </div>
    @endif

    <x-liquidity-notes :liquidity="$liquidity" />
</div>
