@props(['liquidity'])

{{-- Hasta cuándo tiene que alcanzar la cifra de hoy (ADR-0040). Recibe `liquidity`. --}}
@if ($liquidity['payday_known'])
    Hasta tu pago del {{ $liquidity['payday']->format('d/m/Y') }}
    @if ($liquidity['days'] === 1)
        · llega mañana.
    @else
        · faltan {{ $liquidity['days'] }} días.
    @endif
@else
    Para {{ $liquidity['days'] === 1 ? 'el día que queda' : 'los '.$liquidity['days'].' días que quedan' }} del mes.
@endif
