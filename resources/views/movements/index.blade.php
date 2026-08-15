@extends('layouts.app', ['title' => 'Movimientos'])

@section('content')
    <x-flash-messages />

    <div class="d-flex justify-content-between align-items-center mb-4">
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

    {{-- Filtros --}}
    <div class="card border-0 mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('movements.index') }}" class="row g-2 align-items-end">
                <div class="col-6 col-md">
                    <label class="form-label small fw-semibold" for="type">Tipo</label>
                    <select name="type" id="type" class="form-select form-select-sm">
                        <option value="" @selected(blank($filters['type']))>Todos</option>
                        <option value="income" @selected($filters['type'] === 'income')>Ingresos</option>
                        <option value="expense" @selected($filters['type'] === 'expense')>Gastos</option>
                    </select>
                </div>
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

    {{-- Lista --}}
    <div class="card border-0">
        @if ($movements->isEmpty())
            <div class="card-body text-center text-muted py-5">
                <i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>
                No hay movimientos que coincidan con los filtros.
            </div>
        @else
            <div class="list-group list-group-flush">
                @foreach ($movements as $m)
                    @php
                        $isIncome = $m['type'] === 'income';
                        $editRoute = $isIncome ? route('incomes.edit', ['income' => $m['id']]) : route('expenses.edit', ['expense' => $m['id']]);
                        $destroyRoute = $isIncome ? route('incomes.destroy', ['income' => $m['id']]) : route('expenses.destroy', ['expense' => $m['id']]);
                    @endphp
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center gap-2 min-w-0">
                            <i class="bi {{ $isIncome ? 'bi-arrow-down-left-circle text-success' : 'bi-arrow-up-right-circle text-danger' }} fs-5"></i>
                            <div class="min-w-0">
                                <div class="fw-semibold text-truncate">
                                    {{ $m['description'] ?: ($isIncome ? 'Ingreso' : 'Gasto') }}
                                </div>
                                <div class="small text-muted text-truncate">
                                    {{ $m['category_name'] }}
                                    @if ($m['account_name']) · {{ $m['account_name'] }} @endif
                                    @if ($m['user_name']) · {{ $m['user_name'] }} @endif
                                    · {{ $m['date']->format('d/m/Y') }}
                                </div>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="fw-bold {{ $isIncome ? 'text-success' : 'text-danger' }} text-nowrap">
                                {{ $isIncome ? '+' : '−' }}@money($m['amount'])
                            </span>
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
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
@endsection
