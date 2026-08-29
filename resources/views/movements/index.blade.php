@extends('layouts.app', ['title' => 'Movimientos'])

@php
    $typeChips = [
        'Todos' => null,
        'Gastos' => 'expense',
        'Ingresos' => 'income',
    ];
    // El balance es de todo el filtro, no de la página visible (viene del
    // Service en `filterTotals`): cargar más páginas no lo cambia.
    $filterBalance = $filterTotals['balance'];
    $hasAdvancedFilters = $filters['category_id'] || $filters['account_id'] || $filters['user_id'] || $filters['from'] || $filters['to'];
@endphp

@section('content')
    <x-flash-messages />

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0"><i class="bi bi-arrow-left-right me-2"></i>Movimientos</h1>
        <div class="dropdown">
            <button class="btn btn-finlia dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-plus-lg me-1"></i> Registrar
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="{{ route('expenses.create') }}"><i class="bi bi-dash-circle text-danger me-2"></i>Gasto</a></li>
                <li><a class="dropdown-item" href="{{ route('incomes.create') }}"><i class="bi bi-plus-circle text-success me-2"></i>Ingreso</a></li>
            </ul>
        </div>
    </div>

    {{-- Chips de tipo + acceso a filtros avanzados --}}
    <div class="chip-row mb-3">
        @foreach ($typeChips as $label => $value)
            <a href="{{ request()->fullUrlWithQuery(['type' => $value]) }}"
               class="chip {{ $filters['type'] === $value ? 'active' : '' }}">{{ $label }}</a>
        @endforeach
        <button type="button" class="chip {{ $hasAdvancedFilters ? 'active' : '' }}"
                data-bs-toggle="collapse" data-bs-target="#filtrosAvanzados"
                aria-expanded="{{ $hasAdvancedFilters ? 'true' : 'false' }}" aria-controls="filtrosAvanzados">
            <i class="bi bi-sliders2"></i> Filtros
        </button>
    </div>

    {{-- Filtros avanzados (categoría, cuenta, usuario, rango de fechas) --}}
    <div class="collapse {{ $hasAdvancedFilters ? 'show' : '' }}" id="filtrosAvanzados">
        <div class="card border-0 mb-3">
            <div class="card-body">
                <form method="GET" action="{{ route('movements.index') }}" class="row g-2 align-items-end">
                    <input type="hidden" name="type" value="{{ $filters['type'] }}">
                    <div class="col-6 col-md">
                        <label class="form-label small fw-semibold" for="category_id">Categoría</label>
                        <select name="category_id" id="category_id" class="form-select form-select-sm">
                            <option value="">Todas</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}" @selected((string) $filters['category_id'] === (string) $category->id)>{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6 col-md">
                        <label class="form-label small fw-semibold" for="account_id">Cuenta</label>
                        <select name="account_id" id="account_id" class="form-select form-select-sm">
                            <option value="">Todas</option>
                            @foreach ($accounts as $account)
                                <option value="{{ $account->id }}" @selected((string) $filters['account_id'] === (string) $account->id)>{{ $account->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6 col-md">
                        <label class="form-label small fw-semibold" for="user_id">Usuario</label>
                        <select name="user_id" id="user_id" class="form-select form-select-sm">
                            <option value="">Todos</option>
                            @foreach ($members as $member)
                                <option value="{{ $member->id }}" @selected((string) $filters['user_id'] === (string) $member->id)>{{ $member->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small fw-semibold" for="from">Desde</label>
                        <input type="date" name="from" id="from" class="form-control form-control-sm" value="{{ $filters['from'] ?? '' }}">
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small fw-semibold" for="to">Hasta</label>
                        <input type="date" name="to" id="to" class="form-control form-control-sm" value="{{ $filters['to'] ?? '' }}">
                    </div>
                    <div class="col-12 d-flex gap-2">
                        <button type="submit" class="btn btn-sm btn-outline-finlia"><i class="bi bi-funnel me-1"></i>Filtrar</button>
                        <a href="{{ route('movements.index') }}" class="btn btn-sm btn-outline-secondary">Limpiar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @if ($movements->isNotEmpty())
        <div class="d-flex justify-content-between align-items-center px-3 py-2 mb-3 rounded-4"
             style="background-color: var(--finlia-surface); border: 1px solid var(--finlia-glass-border);">
            <span class="small fw-semibold text-muted">Balance del filtro</span>
            <span class="fw-bold {{ $filterBalance >= 0 ? 'text-success' : 'text-danger' }} money-figure">
                {{ $filterBalance >= 0 ? '+' : '−' }} @money(abs($filterBalance))
            </span>
        </div>
    @endif

    {{-- Lista agrupada por día, paginada con "Cargar más" --}}
    @if ($movements->isEmpty())
        <div class="card border-0">
            <div class="card-body text-center text-muted py-5">
                <i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>
                No hay movimientos que coincidan con los filtros.
            </div>
        </div>
    @else
        <div id="lista-movimientos">
            @include('movements._groups')
        </div>

        <script>
            // "Cargar más": pide la siguiente página de esta misma ruta (con
            // los filtros actuales en la URL) y anexa los grupos que devuelve.
            // Delegación sobre el contenedor: el botón se reemplaza en cada carga.
            (function () {
                var lista = document.getElementById('lista-movimientos');
                if (!lista) return;

                lista.addEventListener('click', function (e) {
                    var btn = e.target.closest('#cargarMasBtn');
                    if (!btn || btn.disabled) return;

                    btn.disabled = true;

                    var params = new URLSearchParams(window.location.search);
                    params.set('offset', btn.dataset.nextOffset);

                    fetch(window.location.pathname + '?' + params.toString(), {
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    })
                        .then(function (r) {
                            return r.ok ? r.text() : Promise.reject(new Error('HTTP ' + r.status));
                        })
                        .then(function (html) {
                            var wrapper = document.getElementById('cargarMasWrapper');
                            if (wrapper) wrapper.remove();

                            lista.insertAdjacentHTML('beforeend', html);
                        })
                        .catch(function () {
                            btn.disabled = false; // reintentar si falla la red
                        });
                });
            })();
        </script>
    @endif
@endsection
