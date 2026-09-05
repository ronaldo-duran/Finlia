@php
    $showActions = $showActions ?? false;
    $isIncome = $m['type'] === 'income';
    $isTransfer = $m['type'] === 'transfer';
    $icon = $isIncome ? 'bi-arrow-down-left' : ($isTransfer ? 'bi-arrow-left-right' : 'bi-arrow-up-right');
    $tint = $m['category_color'] ?? null;

    // Transferencias: tinte de marca neutral (no ingreso ni gasto).
    if ($isTransfer) {
        $tint = null;
        $iconBg = 'rgba(var(--finlia-primary-rgb), .10)';
        $iconColor = 'var(--finlia-primary)';
    } else {
        $iconBg = $tint ? $tint.'26' : 'rgba(var(--finlia-primary-rgb), .12)';
        $iconColor = $tint ?? 'var(--finlia-primary)';
    }
@endphp

<div class="list-group-item d-flex justify-content-between align-items-center gap-2">
    <div class="d-flex align-items-center gap-2 gap-md-3 min-w-0">
        <div class="rounded-3 d-flex align-items-center justify-content-center flex-shrink-0"
             style="width: 38px; height: 38px;
                    background-color: {{ $iconBg }};
                    color: {{ $iconColor }};">
            <i class="bi {{ $icon }}"></i>
        </div>
        <div class="min-w-0">
            <div class="fw-semibold text-truncate">
                {{ $m['description'] ?: ($isIncome ? 'Ingreso' : ($isTransfer ? 'Transferencia' : 'Gasto')) }}
            </div>
            {{-- Sin text-truncate: en móvil ocultaba la fecha.
                 Prefiere envolver a perder información. --}}
            <div class="small text-muted">
                @if (!$isTransfer)
                    {{ $m['category_name'] }}
                    @if ($m['account_name']) · {{ $m['account_name'] }} @endif
                @else
                    {{ $m['account_name'] }}
                @endif
                @if ($showActions && $m['user_name']) · {{ $m['user_name'] }} @endif
                · {{ $m['date']->format('d/m/Y') }}
            </div>
        </div>
    </div>
    <div class="d-flex align-items-center gap-2 flex-shrink-0">
        @if ($isTransfer)
            <span class="fw-bold text-muted text-nowrap money-figure">
                @money($m['amount'])
            </span>
        @else
            <span class="fw-bold {{ $isIncome ? 'text-success' : 'text-danger' }} text-nowrap money-figure">
                {{ $isIncome ? '+' : '−' }}@money($m['amount'])
            </span>
        @endif
        @if ($showActions)
            @if ($isTransfer)
                <a href="{{ route('transfers.edit', ['transfer' => $m['id']]) }}" class="btn btn-sm btn-icon" aria-label="Editar">
                    <i class="bi bi-pencil"></i>
                </a>
                <form method="POST" action="{{ route('transfers.destroy', ['transfer' => $m['id']]) }}"
                      data-confirm="¿Eliminar esta transferencia? Se revertirá el movimiento en ambas cuentas.">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-sm btn-icon text-danger" aria-label="Eliminar">
                        <i class="bi bi-trash"></i>
                    </button>
                </form>
            @else
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
        @endif
    </div>
</div>
