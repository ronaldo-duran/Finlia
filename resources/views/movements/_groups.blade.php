{{-- Grupos por día de una página de movimientos + botón "Cargar más".
     Lo usa la pantalla completa y la respuesta parcial del botón: lo que
     este parcial devuelve es exactamente lo que se anexa a la lista. --}}

@php
    $groups = $movements->groupBy(fn ($m) => $m['date']->format('Y-m-d'));
@endphp

<div class="d-flex flex-column gap-3">
    @foreach ($groups as $day => $items)
        @php
            $dayTotal = $items->sum(fn ($m) => $m['type'] === 'income' ? $m['amount'] : -$m['amount']);
        @endphp
        <div>
            <div class="d-flex justify-content-between align-items-baseline mb-2">
                <span class="day-group-label">{{ ucfirst($items->first()['date']->locale('es')->isoFormat('dddd D [de] MMMM')) }}</span>
                <span class="small fw-semibold {{ $dayTotal >= 0 ? 'text-success' : 'text-danger' }} budget-figures">
                    {{ $dayTotal >= 0 ? '+' : '−' }}@money(abs($dayTotal))
                </span>
            </div>
            <div class="card border-0">
                <div class="list-group list-group-flush">
                    @foreach ($items as $m)
                        @include('movements._item', ['m' => $m, 'showActions' => true])
                    @endforeach
                </div>
            </div>
        </div>
    @endforeach
</div>

@if ($hasMore)
    {{-- A la derecha y con aire: centrado quedaba por detrás del botón
         flotante "+" de la barra inferior en móvil (que es fijo y centrado). --}}
    <div class="d-flex justify-content-end mt-3 mb-2 pe-1" id="cargarMasWrapper">
        <button type="button" class="btn btn-outline-finlia" id="cargarMasBtn"
                data-next-offset="{{ $nextOffset }}">
            <i class="bi bi-arrow-down-circle me-1"></i> Cargar más
        </button>
    </div>
@endif
