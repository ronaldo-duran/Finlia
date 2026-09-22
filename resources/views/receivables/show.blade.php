@extends('layouts.app', ['title' => $receivable->name])

@php
    use App\Enums\ReceivablePaymentType;
    $hoy = now(config('app.timezone'))->startOfDay();
    $due = $receivable->due_date;
    $overdue = $due !== null && $due->lt($hoy) && $receivable->status->isOutstanding();
@endphp

@section('content')
    <x-flash-messages />

    <div class="d-flex justify-content-between align-items-start mb-4 gap-2">
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('receivables.index') }}" class="btn btn-icon" aria-label="Volver">
                <i class="bi bi-arrow-left"></i>
            </a>
            <div>
                <h1 class="h3 mb-0">
                    <i class="bi bi-inbox-fill me-1"></i>{{ $receivable->name }}
                </h1>
                <p class="text-muted mb-0">
                    <i class="bi bi-person-check me-1"></i>{{ $receivable->debtor_name }}
                    @if ($receivable->debtorUser)
                        <span class="badge bg-finlia-subtle text-finlia ms-1">del hogar</span>
                    @endif
                    · <span class="badge {{ $receivable->status->badgeClass() }}">{{ $receivable->status->label() }}</span>
                </p>
            </div>
        </div>
        <a href="{{ route('receivables.edit', $receivable) }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-pencil me-1"></i> Editar
        </a>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-md-4">
            <div class="card border-0 h-100"><div class="card-body">
                <div class="text-muted small text-uppercase">Saldo pendiente</div>
                <div class="fs-5 fw-bold text-finlia">@money($receivable->current_balance)</div>
            </div></div>
        </div>
        <div class="col-6 col-md-4">
            <div class="card border-0 h-100"><div class="card-body">
                <div class="text-muted small text-uppercase">Monto original</div>
                <div class="fs-5 fw-bold">@money($receivable->original_amount)</div>
            </div></div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card border-0 h-100"><div class="card-body">
                <div class="text-muted small text-uppercase">Fecha de cobro</div>
                <div class="fs-5 fw-bold {{ $overdue ? 'text-danger' : '' }}">
                    @if ($due)
                        {{ $due->format('d/m/Y') }}
                        @if ($overdue) <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>@endif
                    @else
                        —
                    @endif
                </div>
                @if ($overdue)
                    <div class="small text-danger mt-1">Vencido. Edita para posponer.</div>
                @endif
            </div></div>
        </div>
    </div>

    <div class="card border-0 mb-4"><div class="card-body">
        <div class="d-flex justify-content-between small mb-1">
            <span class="text-muted">Progreso de cobro</span>
            <span class="fw-semibold">@percent($receivable->progressPercent())</span>
        </div>
        <div class="progress" style="height:.6rem" role="progressbar"
             aria-label="Progreso de cobro" aria-valuenow="{{ $receivable->progressPercent() }}"
             aria-valuemin="0" aria-valuemax="100">
            <div class="progress-bar bg-success" style="width: {{ $receivable->progressPercent() }}%"></div>
        </div>
        @if ($receivable->description)
            <p class="small text-muted mt-3 mb-0">{{ $receivable->description }}</p>
        @endif
    </div></div>

    <div class="row g-3">
        <div class="col-12 col-lg-7">
            <div class="card border-0">
                <div class="card-header border-0 bg-transparent fw-semibold">
                    <i class="bi bi-clock-history me-1"></i> Historial de cobros
                </div>
                <div class="card-body p-0">
                    @forelse ($payments as $payment)
                        <div class="d-flex justify-content-between align-items-center gap-2 px-3 py-2 border-bottom">
                            <div>
                                <span class="fw-semibold">@money($payment->amount)</span>
                                <span class="badge {{ $payment->type->badgeClass() }} ms-1">{{ $payment->type->label() }}</span>
                                <div class="small text-muted">
                                    {{ $payment->date->format('d/m/Y') }}
                                    @if ($payment->income_id && $payment->account)
                                        · <i class="bi bi-link-45deg"></i> ingreso en {{ $payment->account->name }}
                                    @elseif ($payment->type === ReceivablePaymentType::Received)
                                        · sin movimiento asociado
                                    @endif
                                </div>
                                @if ($payment->notes)
                                    <div class="small text-muted fst-italic">{{ $payment->notes }}</div>
                                @endif
                            </div>
                            <form method="POST" action="{{ route('receivables.payments.destroy', [$receivable, $payment]) }}"
                                  data-confirm="¿Eliminar este cobro? El saldo volverá a subir y se borrará el ingreso asociado.">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-icon text-danger" aria-label="Eliminar cobro">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </div>
                    @empty
                        <p class="text-muted small mb-0 p-3">
                            Todavía no has registrado cobros de esta cuenta.
                        </p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-5">
            <div class="card border-0 mb-3">
                <div class="card-header border-0 bg-transparent fw-semibold">
                    <i class="bi bi-cash-coin me-1"></i> Registrar cobro
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('receivables.payments.store', $receivable) }}">
                        @csrf
                        <div class="row g-2">
                            <div class="col-12">
                                <label for="col_amount" class="form-label small fw-semibold">Monto</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" name="amount" id="col_amount" required step="0.01" min="0.01"
                                           class="form-control @error('amount') is-invalid @enderror"
                                           value="{{ old('amount', $receivable->current_balance > 0 ? $receivable->current_balance : null) }}">
                                </div>
                                @error('amount')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-6">
                                <label for="col_date" class="form-label small fw-semibold">Fecha</label>
                                <input type="date" name="date" id="col_date" required
                                       max="{{ now(config('app.timezone'))->format('Y-m-d') }}"
                                       class="form-control @error('date') is-invalid @enderror"
                                       value="{{ old('date', now(config('app.timezone'))->format('Y-m-d')) }}">
                                @error('date')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-6">
                                <label for="col_type" class="form-label small fw-semibold">Tipo</label>
                                <select name="type" id="col_type" required class="form-select">
                                    @foreach (ReceivablePaymentType::cases() as $case)
                                        <option value="{{ $case->value }}" @selected(old('type', 'received') === $case->value)>
                                            {{ $case->label() }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12">
                                <label for="col_account" class="form-label small fw-semibold">¿A qué cuenta entró?</label>
                                <select name="account_id" id="col_account" class="form-select">
                                    <option value="">No registrar ingreso</option>
                                    @foreach ($accounts as $account)
                                        <option value="{{ $account->id }}" @selected((int) old('account_id', null) === $account->id)>
                                            {{ $account->name }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="form-text">Solo aplica a «Cobro recibido»: se registra el ingreso y sube su saldo.</div>
                            </div>
                            <div class="col-12">
                                <label for="col_category" class="form-label small fw-semibold">Categoría <span class="text-muted fw-normal">(opcional)</span></label>
                                <select name="category_id" id="col_category" class="form-select">
                                    <option value="">Sin categoría</option>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category->id }}" @selected((int) old('category_id', null) === $category->id)>
                                            {{ $category->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12">
                                <label for="col_notes" class="form-label small fw-semibold">Notas <span class="text-muted fw-normal">(opcional)</span></label>
                                <input type="text" name="notes" id="col_notes" maxlength="2000"
                                       class="form-control" value="{{ old('notes') }}">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-finlia w-100 mt-3">Registrar cobro</button>
                    </form>
                </div>
            </div>

            <form method="POST" action="{{ route('receivables.destroy', $receivable) }}"
                  data-confirm="¿Eliminar la cuenta por cobrar «{{ $receivable->name }}»?">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-sm btn-outline-danger w-100">
                    <i class="bi bi-trash me-1"></i> Eliminar cuenta por cobrar
                </button>
            </form>
        </div>
    </div>

@endsection
