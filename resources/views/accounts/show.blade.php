@extends('layouts.app', ['title' => $account->name])

@section('content')
    <x-flash-messages />

    <div class="d-flex justify-content-between align-items-start mb-4 gap-2">
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('accounts.index') }}" class="btn btn-icon" aria-label="Volver">
                <i class="bi bi-arrow-left"></i>
            </a>
            <div>
                <h1 class="h3 mb-0">
                    <i class="bi {{ $account->type->icon() }} me-1"></i>{{ $account->name }}
                </h1>
                <p class="text-muted mb-0">
                    {{ $account->type->label() }} · {{ $account->currency }}
                    @if (! $account->is_active) · <span class="badge text-bg-secondary">Inactiva</span> @endif
                </p>
            </div>
        </div>
        <a href="{{ route('accounts.edit', $account) }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-pencil me-1"></i> Editar
        </a>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card border-0 h-100"><div class="card-body">
                <div class="text-muted small text-uppercase">Saldo inicial</div>
                <div class="fs-5 fw-bold">@money($account->initial_balance)</div>
            </div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 h-100"><div class="card-body">
                <div class="text-muted small text-uppercase">Saldo actual</div>
                <div class="fs-5 fw-bold {{ $account->current_balance >= 0 ? 'text-success' : 'text-danger' }}">@money($account->current_balance)</div>
            </div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 h-100"><div class="card-body">
                <div class="text-muted small text-uppercase">Ingresos</div>
                <div class="fs-5 fw-bold text-success">{{ $account->incomes->count() }}</div>
            </div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 h-100"><div class="card-body">
                <div class="text-muted small text-uppercase">Gastos</div>
                <div class="fs-5 fw-bold text-danger">{{ $account->expenses->count() }}</div>
            </div></div>
        </div>
    </div>

    @if ($account->notes)
        <div class="card border-0 mb-4"><div class="card-body">
            <div class="text-muted small text-uppercase mb-1">Notas</div>
            <p class="mb-0">{{ $account->notes }}</p>
        </div></div>
    @endif

    <div class="card border-0">
        <div class="card-header border-0 bg-transparent fw-semibold"><i class="bi bi-clock-history me-1"></i> Movimientos recientes</div>
        @php
            $movimientos = collect();
            foreach ($account->incomes as $i) { $movimientos->push(['type' => 'income', 'amount' => $i->amount, 'date' => $i->date, 'description' => $i->description, 'category' => $i->category?->name]); }
            foreach ($account->expenses as $e) { $movimientos->push(['type' => 'expense', 'amount' => $e->amount, 'date' => $e->date, 'description' => $e->description, 'category' => $e->category?->name]); }
            $movimientos = $movimientos->sortByDesc(fn ($m) => $m['date']->getTimestamp());
        @endphp
        @if ($movimientos->isEmpty())
            <div class="card-body text-center text-muted py-4">Sin movimientos en esta cuenta.</div>
        @else
            <div class="list-group list-group-flush">
                @foreach ($movimientos as $m)
                    @php $isIncome = $m['type'] === 'income'; @endphp
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <div class="fw-semibold">{{ $m['description'] ?: ($isIncome ? 'Ingreso' : 'Gasto') }}</div>
                            <div class="small text-muted">{{ $m['category'] ?? 'Sin categoría' }} · {{ $m['date']->format('d/m/Y') }}</div>
                        </div>
                        <span class="fw-bold {{ $isIncome ? 'text-success' : 'text-danger' }}">
                            {{ $isIncome ? '+' : '−' }}@money($m['amount'])
                        </span>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
@endsection
