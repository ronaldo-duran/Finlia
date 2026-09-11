{{--
    Variante "Enfoque" (1a): un número, un gesto. La tarjeta hero responde
    a una sola pregunta: cuánto puedo gastar hoy.

    Aquí había además dos botones, "Gasto" e "Ingreso", que duplicaban dos de
    las cinco acciones del "+" flotante. Se quitaron: al estar el "+" fijo
    sobre el contenido, en pantallas de teléfono acababa cayendo encima de
    ellos y el panel ofrecía dos entradas para lo mismo, una de ellas
    intermitente. El "+" es ahora la única entrada para registrar, y da acceso
    a las cinco acciones en vez de a dos.
--}}
@php
    // "Puedes gastar hoy" sale del saldo real hasta el próximo cobro (ADR-0040).
    $liquidity = $budgetSummary['liquidity'];
    $heroAmount = $isNegative ? $liquidity['shortfall'] : $liquidity['daily_allowance'];
    $percent = $budgetSummary['consumed_percent'];
@endphp

{{-- Cobre = lo disponible (docs/BRAND.md): esta cifra es la razón de ser
     de la regla, así que usa el acento de marca en vez del primario. --}}
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

    <p class="text-muted small mb-0 mt-2" data-testid="available-money-horizon">
        @switch($liquidity['status'])
            @case('short')
                Es lo que te falta para cubrir los pagos que vencen antes del {{ $liquidity['payday']->format('d/m/Y') }}.
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
