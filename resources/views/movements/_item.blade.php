@php
    $showActions = $showActions ?? false;
    $isIncome = $m['type'] === 'income';
    $icon = $isIncome ? 'bi-arrow-down-left' : 'bi-arrow-up-right';
    $tint = $m['category_color'] ?? null;
@endphp

<div class="list-group-item d-flex justify-content-between align-items-center gap-2">
    <div class="d-flex align-items-center gap-2 gap-md-3 min-w-0">
        <div class="rounded-3 d-flex align-items-center justify-content-center flex-shrink-0"
             style="width: 38px; height: 38px;
                    background-color: {{ $tint ? $tint.'26' : 'rgba(var(--finlia-primary-rgb), .12)' }};
                    color: {{ $tint ?? 'var(--finlia-primary)' }};">
            <i class="bi {{ $icon }}"></i>
        </div>
        <div class="min-w-0">
            <div class="fw-semibold text-truncate">
                {{ $m['description'] ?: ($isIncome ? 'Ingreso' : 'Gasto') }}
            </div>
            {{-- Sin text-truncate: en móvil ocultaba la fecha.
                 Prefiere envolver a perder información. --}}
            <div class="small text-muted">
                {{ $m['category_name'] }}
                @if ($m['account_name']) · {{ $m['account_name'] }} @endif
                @if ($showActions && $m['user_name']) · {{ $m['user_name'] }} @endif
                · {{ $m['date']->format('d/m/Y') }}
            </div>
        </div>
    </div>
    <div class="d-flex align-items-center gap-2 flex-shrink-0">
        <span class="fw-bold {{ $isIncome ? 'text-success' : 'text-danger' }} text-nowrap money-figure">
            {{ $isIncome ? '+' : '−' }}@money($m['amount'])
        </span>
        @if ($showActions)
            @php
                $editRoute = $isIncome ? route('incomes.edit', ['income' => $m['id']]) : route('expenses.edit', ['expense' => $m['id']]);
                $destroyRoute = $isIncome ? route('incomes.destroy', ['income' => $m['id']]) : route('expenses.destroy', ['expense' => $m['id']]);
            @endphp
            <a href="{{ $editRoute }}" class="btn btn-sm btn-icon" aria-label="Editar">
                <i class="bi bi-pencil"></i>
            </a>
            <form method="POST" action="{{ $destroyRoute }}" data-confirm="¿Eliminar este movimiento?">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-sm btn-icon text-danger" aria-label="Eliminar">
                    <i class="bi bi-trash"></i>
                </button>
            </form>
        @endif
    </div>
</div>
