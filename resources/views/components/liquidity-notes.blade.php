@props(['liquidity'])

{{--
    Avisos que acompañan al "puedes gastar hoy" (ADR-0040): pagos esperados
    que no han llegado y lo que falta configurar para que la cifra sea exacta.
    Recibe el array `liquidity` de BudgetCalculatorService.
--}}
@foreach ($liquidity['pending_incomes'] as $pending)
    <div class="small mt-3 d-flex gap-2" data-testid="pending-income">
        <i class="bi bi-hourglass-split"></i>
        <div>
            @if ($pending['is_today'])
                Hoy esperas tu pago «{{ $pending['name'] }}».
            @else
                Tu pago «{{ $pending['name'] }}» del {{ $pending['date']->format('d/m/Y') }} aún no aparece.
            @endif
            Hasta que lo registres, la cifra solo cuenta con lo que ya tienes.
            <a href="{{ route('incomes.create') }}" class="fw-semibold">Registrar ingreso</a>
        </div>
    </div>
@endforeach

@if (! $liquidity['has_accounts'])
    <div class="small mt-3 d-flex gap-2">
        <i class="bi bi-info-circle"></i>
        <div>
            Registra tus cuentas con el saldo que tienes hoy: es la base de esta cifra.
            <a href="{{ route('accounts.create') }}" class="fw-semibold">Añadir cuenta</a>
        </div>
    </div>
@elseif (! $liquidity['has_expected_income'])
    <div class="small mt-3 d-flex gap-2">
        <i class="bi bi-info-circle"></i>
        <div>
            Dinos cuánto y qué día te pagan para saber hasta cuándo te tiene que alcanzar.
            <a href="{{ route('expected-incomes.index') }}" class="fw-semibold">Configurar</a>
        </div>
    </div>
@elseif (! $liquidity['payday_known'])
    <div class="small mt-3 d-flex gap-2">
        <i class="bi bi-info-circle"></i>
        <div>
            Indica qué día te pagan «{{ $liquidity['payday_income'] }}» para saber hasta cuándo te tiene que alcanzar.
            <a href="{{ route('expected-incomes.index') }}" class="fw-semibold">Configurar</a>
        </div>
    </div>
@endif
