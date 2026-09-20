@props(['liquidity'])

{{--
    Desglose del "te falta plata antes de tu próximo pago" (ADR-0040):
    saldo real, apartado en metas y compromisos del ciclo, para que el
    faltante no parezca sacado de la manga y no confunda con el KPI de
    "Deuda total" de al lado (ese muestra el saldo entero, el hero solo
    resta la cuota del ciclo).

    Solo se muestra en `status = short`: en `over_plan` no aplica y en
    `ok` sería ruido. Las filas con importe cero se ocultan para no
    ensuciar la tarjeta cuando no hay metas o compromisos.
--}}
@if ($liquidity['status'] === 'short')
    @php
        $balance = $liquidity['current_balance'];
        $setAside = $liquidity['set_aside'];
        $reserved = $liquidity['reserved']['total'];
    @endphp
    <dl class="liquidity-breakdown row g-0 small mt-3 mb-0" data-testid="liquidity-breakdown">
        <dt class="col-8 fw-normal text-muted">Saldo en cuentas</dt>
        <dd class="col-4 mb-1 text-end">@money($balance)</dd>

        @if ($setAside > 0)
            <dt class="col-8 fw-normal text-muted">Apartado en metas</dt>
            <dd class="col-4 mb-1 text-end">−@money($setAside)</dd>
        @endif

        @if ($reserved > 0)
            <dt class="col-8 fw-normal text-muted">Compromisos del ciclo</dt>
            <dd class="col-4 mb-1 text-end">−@money($reserved)</dd>
        @endif

        <dt class="col-8 pt-1 border-top fw-semibold">Te faltan</dt>
        <dd class="col-4 pt-1 mb-0 text-end border-top fw-semibold text-danger">@money($liquidity['shortfall'])</dd>
    </dl>
@endif
