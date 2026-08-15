@extends('layouts.app', ['title' => 'Cuentas'])

@section('content')
    <x-flash-messages />

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><i class="bi bi-wallet me-2"></i>Cuentas</h1>
        <a href="{{ route('accounts.create') }}" class="btn btn-finlia">
            <i class="bi bi-plus-lg me-1"></i> Nueva cuenta
        </a>
    </div>

    @if ($accounts->isEmpty())
        <div class="card border-0">
            <div class="card-body text-center text-muted py-5">
                <i class="bi bi-wallet2 fs-1 d-block mb-2 opacity-50"></i>
                No tienes cuentas todavía.
                <div class="mt-3">
                    <a href="{{ route('accounts.create') }}" class="btn btn-outline-finlia">Crea tu primera cuenta</a>
                </div>
            </div>
        </div>
    @else
        <div class="row g-3">
            @foreach ($accounts as $account)
                <div class="col-12 col-sm-6 col-xl-4">
                    <div class="card border-0 h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="rounded-3 bg-finlia-subtle text-finlia d-flex align-items-center justify-content-center"
                                          style="width:40px;height:40px;">
                                        <i class="bi {{ $account->type->icon() }}"></i>
                                    </span>
                                    <div>
                                        <div class="fw-semibold">{{ $account->name }}</div>
                                        <div class="small text-muted">{{ $account->type->label() }}</div>
                                    </div>
                                </div>
                                @if (! $account->is_active)
                                    <span class="badge text-bg-secondary">Inactiva</span>
                                @endif
                            </div>
                            <div class="mb-3">
                                <div class="text-muted small text-uppercase">Saldo actual</div>
                                <div class="fs-4 fw-bold {{ $account->current_balance >= 0 ? 'text-success' : 'text-danger' }}">
                                    @money($account->current_balance)
                                </div>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="small text-muted">
                                    <i class="bi bi-arrow-down-left me-1"></i>{{ $account->incomes_count }}
                                    <i class="bi bi-arrow-up-right ms-2 me-1"></i>{{ $account->expenses_count }}
                                </span>
                                <div class="d-flex gap-1">
                                    <a href="{{ route('accounts.show', $account) }}" class="btn btn-sm btn-icon" aria-label="Ver">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="{{ route('accounts.edit', $account) }}" class="btn btn-sm btn-icon" aria-label="Editar">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
@endsection
