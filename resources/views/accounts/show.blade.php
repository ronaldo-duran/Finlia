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

    {{-- Datos de tarjeta de crédito (Épica 6, ADR-0002).
         Nunca se pide ni se guarda número completo, CVV ni PIN. --}}
    @if ($account->type === \App\Enums\AccountType::CreditCard)
        @php $card = $account->creditCard; @endphp
        <div class="card border-0 mb-4">
            <div class="card-header border-0 bg-transparent fw-semibold">
                <i class="bi bi-credit-card me-1"></i> Datos de la tarjeta
            </div>
            <div class="card-body">
                @if ($card !== null)
                    <div class="row g-3 mb-3">
                        <div class="col-6 col-md-3">
                            <div class="text-muted small text-uppercase">Cupo total</div>
                            <div class="fw-bold">@money($card->credit_limit)</div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="text-muted small text-uppercase">Cupo disponible</div>
                            <div class="fw-bold">@money($card->available_credit)</div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="text-muted small text-uppercase">Día de corte</div>
                            <div class="fw-bold">{{ $card->statement_date ?? '—' }}</div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="text-muted small text-uppercase">Día límite de pago</div>
                            <div class="fw-bold">{{ $card->payment_due_date ?? '—' }}</div>
                        </div>
                    </div>
                    @php $uso = $card->utilizationPercent(); @endphp
                    <div class="progress" style="height:.5rem" role="progressbar"
                         aria-label="Cupo utilizado" aria-valuenow="{{ $uso }}" aria-valuemin="0" aria-valuemax="100">
                        <div class="progress-bar {{ $uso > 30 ? 'bg-warning' : 'bg-success' }}" style="width: {{ $uso }}%"></div>
                    </div>
                    <div class="small text-muted mt-1">
                        Has usado {{ str_replace('.', ',', (string) $uso) }} % del cupo.
                        @if ($uso > 30) Por encima del 30 % suele penalizar tu historial crediticio. @endif
                    </div>
                @endif

                <form method="POST" action="{{ route('accounts.credit-card.update', $account) }}" class="mt-3">
                    @csrf
                    @method('PUT')
                    <div class="row g-2 align-items-end">
                        <div class="col-12 col-sm-4">
                            <label for="credit_limit" class="form-label small fw-semibold">Cupo total</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" name="credit_limit" id="credit_limit" required step="0.01" min="0.01"
                                       class="form-control @error('credit_limit') is-invalid @enderror"
                                       value="{{ old('credit_limit', $card?->credit_limit) }}">
                            </div>
                            @error('credit_limit')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-6 col-sm-2">
                            <label for="statement_date" class="form-label small fw-semibold">Corte</label>
                            <input type="number" name="statement_date" id="statement_date" min="1" max="31"
                                   class="form-control" value="{{ old('statement_date', $card?->statement_date) }}">
                        </div>
                        <div class="col-6 col-sm-2">
                            <label for="payment_due_date" class="form-label small fw-semibold">Pago</label>
                            <input type="number" name="payment_due_date" id="payment_due_date" min="1" max="31"
                                   class="form-control" value="{{ old('payment_due_date', $card?->payment_due_date) }}">
                        </div>
                        <div class="col-6 col-sm-2">
                            <label for="annual_fee" class="form-label small fw-semibold">Manejo anual</label>
                            <input type="number" name="annual_fee" id="annual_fee" step="0.01" min="0"
                                   class="form-control" value="{{ old('annual_fee', $card?->annual_fee) }}">
                        </div>
                        <div class="col-6 col-sm-2">
                            <button type="submit" class="btn btn-outline-finlia w-100">Guardar</button>
                        </div>
                    </div>
                    <div class="form-text mt-2">
                        <i class="bi bi-shield-lock me-1"></i>
                        Finlia nunca te pedirá el número completo de la tarjeta, el CVV ni el PIN.
                    </div>
                </form>
            </div>
        </div>
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
