@props([
    'summary',
    'compact' => false,
])

@php
    /**
     * Tarjeta principal de la Épica 4: "¿cuánto puedo gastar?".
     * Recibe el array de BudgetCalculatorService::summary().
     */
    $available = $summary['available'];
    $isNegative = $available < 0;
    $scope = $summary['scope'];
@endphp

{{-- Cobre = lo disponible (docs/BRAND.md). --}}
<div class="card border-0 h-100 {{ $isNegative ? 'bg-danger-subtle' : 'bg-finlia-accent-subtle' }}"
     data-testid="available-money">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-1 gap-sm-2 mb-1">
            <span class="text-uppercase small fw-semibold {{ $isNegative ? 'text-danger-emphasis' : 'text-finlia-accent' }}">
                💰 @if ($isNegative) Te has pasado del plan @else Puedes gastar aproximadamente @endif
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
            @money(abs($available))
        </div>

        @if ($summary['days_remaining'] > 0 && ! $isNegative)
            <p class="text-muted small mb-0">
                Son <strong>@money($summary['daily_allowance'])</strong> al día durante los
                <strong>{{ $summary['days_remaining'] }}</strong>
                {{ $summary['days_remaining'] === 1 ? 'día que queda' : 'días que quedan' }}
                {{ $scope->unitLabel() }}.
            </p>
        @elseif ($isNegative)
            <p class="text-danger-emphasis small mb-0">
                Tus gastos y compromisos superan lo que esperas recibir {{ $scope->unitLabel() }}.
            </p>
        @else
            <p class="text-muted small mb-0">Este período ya terminó.</p>
        @endif

        @unless ($summary['has_expected_income'])
            <div class="mt-3 small">
                <i class="bi bi-info-circle me-1"></i>
                Aún no has configurado tus ingresos esperados.
                <a href="{{ route('expected-incomes.index') }}" class="fw-semibold">Configúralos</a>
                para que este número sea fiable.
            </div>
        @endunless
    </div>

    @if ($compact)
        <div class="card-footer border-0 bg-transparent pt-0">
            <a href="{{ route('budgets.index') }}" class="btn btn-sm btn-outline-finlia w-100">
                <i class="bi bi-cash-stack me-1"></i> Ver presupuestos
            </a>
        </div>
    @endif
</div>
