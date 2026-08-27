{{--
    Variante "Control" (1b): el mes de un vistazo. Cabecera con anillo de
    presupuesto consumido + acciones rápidas en grilla.
--}}
@php
    $percent = $budgetSummary['consumed_percent'] ?? 0;
@endphp

<div class="hero-card mb-3 text-white" data-testid="available-money"
     style="background-image: linear-gradient(160deg, var(--finlia-primary), var(--finlia-primary-hover));">
    <div class="d-flex align-items-center gap-3">
        <div class="budget-ring" style="--pct: {{ min(100, $percent) }};">
            <div class="budget-ring-inner">
                <span class="fw-bold">{{ $percent !== null ? number_format($percent, 0) : 0 }}%</span>
                <span class="small" style="opacity: .75; font-size: .625rem;">usado</span>
            </div>
        </div>
        <div class="min-w-0">
            <div class="text-uppercase small fw-semibold" style="opacity: .85;">
                {{ $isNegative ? 'Te has pasado del plan' : 'Disponible este mes' }}
            </div>
            <div class="hero-figure" data-testid="available-money-amount" style="font-size: clamp(1.5rem, 6vw, 2rem);">
                @money(abs($budgetSummary['available']))
            </div>
            @if ($budgetSummary['days_remaining'] > 0 && ! $isNegative)
                <div class="small" style="opacity: .85;">
                    @money($budgetSummary['daily_allowance']) al día · {{ $budgetSummary['days_remaining'] }}
                    {{ $budgetSummary['days_remaining'] === 1 ? 'día' : 'días' }} restantes
                </div>
            @endif
        </div>
    </div>
</div>

<div class="d-flex justify-content-between mb-4 px-2 px-sm-4">
    <a href="{{ route('expenses.create') }}" class="quick-action" aria-label="Registrar gasto">
        <span class="quick-action-icon"><i class="bi bi-dash-circle"></i></span> Gasto
    </a>
    <a href="{{ route('incomes.create') }}" class="quick-action" aria-label="Registrar ingreso">
        <span class="quick-action-icon"><i class="bi bi-plus-circle"></i></span> Ingreso
    </a>
    <a href="{{ route('movements.index') }}" class="quick-action">
        <span class="quick-action-icon"><i class="bi bi-clock-history"></i></span> Movimientos
    </a>
    <a href="{{ route('budgets.index') }}" class="quick-action">
        <span class="quick-action-icon"><i class="bi bi-cash-stack"></i></span> Presupuesto
    </a>
</div>
