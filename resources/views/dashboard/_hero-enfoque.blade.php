{{--
    Variante "Enfoque" (1a): un número, un gesto. La tarjeta hero responde
    a una sola pregunta —cuánto puedo gastar hoy— y dos acciones del mismo
    peso visual la acompañan.
--}}
@php
    $showDaily = $budgetSummary['days_remaining'] > 0 && ! $isNegative;
    $heroAmount = $showDaily ? $budgetSummary['daily_allowance'] : abs($budgetSummary['available']);
    $percent = $budgetSummary['consumed_percent'];
@endphp

{{-- Cobre = lo disponible (docs/BRAND.md): esta cifra es la razón de ser
     de la regla, así que usa el acento de marca en vez del primario. --}}
<div class="hero-card {{ $isNegative ? 'bg-danger-subtle' : 'bg-finlia-accent-subtle' }} mb-3" data-testid="available-money">
    <div class="text-uppercase small fw-semibold {{ $isNegative ? 'text-danger' : 'text-finlia-accent' }} mb-2">
        <i class="bi bi-wallet2 me-1"></i>
        {{ $isNegative ? 'Te has pasado del plan' : ($showDaily ? 'Puedes gastar hoy' : 'Puedes gastar este mes') }}
    </div>
    <div class="hero-figure" data-testid="available-money-amount">@money($heroAmount)</div>

    @if ($showDaily)
        <p class="text-muted small mb-0 mt-2">
            Quedan <strong>@money($budgetSummary['available'])</strong> para los
            {{ $budgetSummary['days_remaining'] }} {{ $budgetSummary['days_remaining'] === 1 ? 'día' : 'días' }} que quedan.
        </p>
    @elseif ($isNegative)
        <p class="text-muted small mb-0 mt-2">Tus gastos superan lo que esperas recibir este mes.</p>
    @endif

    @if ($percent !== null)
        <div class="progress mt-3" role="progressbar" aria-label="Presupuesto consumido"
             aria-valuenow="{{ min(100, $percent) }}" aria-valuemin="0" aria-valuemax="100" style="height: 6px;">
            <div class="progress-bar bg-{{ $budgetSummary['level']->color() }}" style="width: {{ min(100, $percent) }}%"></div>
        </div>
        <div class="d-flex justify-content-between small text-muted mt-2">
            <span>@percent($percent) del presupuesto usado</span>
            <span class="budget-figures">@money($budgetSummary['budget_defined'])</span>
        </div>
    @elseif (! $budgetSummary['has_expected_income'])
        <div class="mt-3 small">
            <i class="bi bi-info-circle me-1"></i>
            Aún no has configurado tus ingresos esperados.
            <a href="{{ route('expected-incomes.index') }}" class="fw-semibold">Configúralos</a>
            para que este número sea fiable.
        </div>
    @endif
</div>

<div class="d-flex gap-2 mb-4">
    <a href="{{ route('expenses.create') }}" class="btn btn-finlia btn-lg flex-fill" aria-label="Registrar gasto">
        <i class="bi bi-dash-circle me-1"></i> Gasto
    </a>
    <a href="{{ route('incomes.create') }}" class="btn btn-outline-finlia btn-lg flex-fill" aria-label="Registrar ingreso">
        <i class="bi bi-plus-circle me-1"></i> Ingreso
    </a>
</div>
