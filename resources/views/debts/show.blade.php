@extends('layouts.app', ['title' => $debt->name])

@php
    /**
     * Épica 6: detalle de una deuda.
     * $projection viene calculada por DebtService: la vista no proyecta nada.
     */
    $commitment = $debt->monthlyCommitment();
@endphp

@section('content')
    <x-flash-messages />

    <div class="d-flex justify-content-between align-items-start mb-4 gap-2">
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('debts.index') }}" class="btn btn-icon" aria-label="Volver">
                <i class="bi bi-arrow-left"></i>
            </a>
            <div>
                <h1 class="h3 mb-0">
                    <i class="bi {{ $debt->type->icon() }} me-1"></i>{{ $debt->name }}
                </h1>
                <p class="text-muted mb-0">
                    {{ $debt->type->label() }}
                    @if ($debt->institution) · {{ $debt->institution }} @endif
                    · <span class="badge {{ $debt->status->badgeClass() }}">{{ $debt->status->label() }}</span>
                </p>
            </div>
        </div>
        <button type="button" class="btn btn-sm btn-outline-secondary"
                data-bs-toggle="modal" data-bs-target="#editDebtModal">
            <i class="bi bi-pencil me-1"></i> Editar
        </button>
    </div>

    {{-- Cifras --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card border-0 h-100"><div class="card-body">
                <div class="text-muted small text-uppercase">Saldo actual</div>
                <div class="fs-5 fw-bold text-danger">@money($debt->current_balance)</div>
            </div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 h-100"><div class="card-body">
                <div class="text-muted small text-uppercase">Monto original</div>
                <div class="fs-5 fw-bold">@money($debt->original_amount)</div>
            </div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 h-100"><div class="card-body">
                <div class="text-muted small text-uppercase">Cuota mensual</div>
                <div class="fs-5 fw-bold">@money($commitment)</div>
            </div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 h-100"><div class="card-body">
                <div class="text-muted small text-uppercase">Tasa anual</div>
                <div class="fs-5 fw-bold">
                    @if ($debt->interest_rate !== null)
                        {{ str_replace('.', ',', rtrim(rtrim(number_format((float) $debt->interest_rate, 3, '.', ''), '0'), '.')) }} %
                    @else
                        —
                    @endif
                </div>
            </div></div>
        </div>
    </div>

    {{-- Progreso y proyección --}}
    <div class="card border-0 mb-4"><div class="card-body">
        <div class="d-flex justify-content-between small mb-1">
            <span class="text-muted">Progreso</span>
            <span class="fw-semibold">{{ str_replace('.', ',', (string) $debt->progressPercent()) }} %</span>
        </div>
        <div class="progress" style="height:.6rem" role="progressbar"
             aria-label="Progreso de pago" aria-valuenow="{{ $debt->progressPercent() }}"
             aria-valuemin="0" aria-valuemax="100">
            <div class="progress-bar bg-success" style="width: {{ $debt->progressPercent() }}%"></div>
        </div>

        <div class="mt-3 small">
            @if ($projection['never_ends'])
                <div class="alert alert-warning mb-0 d-flex gap-2" role="alert">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <div>
                        @if ($commitment <= 0)
                            <strong>Sin cuota registrada</strong> no se puede estimar cuándo terminarías.
                            Añade el pago mínimo o la cuota pactada con «Editar».
                        @else
                            Con esta cuota <strong>no se cubren los intereses</strong>: a este ritmo el saldo
                            no bajaría. Si puedes, sube la cuota o abona extra.
                        @endif
                    </div>
                </div>
            @elseif ($projection['months'] !== null)
                <div class="d-flex gap-2 text-muted">
                    <i class="bi bi-flag-fill text-finlia"></i>
                    <div>
                        Si mantienes este ritmo, terminarías hacia
                        <strong class="text-body">{{ $projection['date']->translatedFormat('F \d\e Y') }}</strong>
                        ({{ $projection['months'] }} {{ $projection['months'] === 1 ? 'mes' : 'meses' }}),
                        pagando cerca de <strong class="text-body">@money($projection['total_interest'])</strong> en intereses.
                        <div class="mt-1"><em>Es una estimación</em>: no contempla cuotas de manejo, seguros, mora ni compras nuevas.</div>
                    </div>
                </div>
            @endif
        </div>
    </div></div>

    <div class="row g-3">
        {{-- Historial de pagos --}}
        <div class="col-12 col-lg-7">
            <div class="card border-0">
                <div class="card-header border-0 bg-transparent fw-semibold">
                    <i class="bi bi-clock-history me-1"></i> Historial de pagos
                </div>
                <div class="card-body p-0">
                    @forelse ($payments as $payment)
                        <div class="d-flex justify-content-between align-items-center gap-2 px-3 py-2 border-bottom">
                            <div>
                                <span class="fw-semibold">@money($payment->amount)</span>
                                <span class="badge {{ $payment->type->badgeClass() }} ms-1">{{ $payment->type->label() }}</span>
                                <div class="small text-muted">
                                    {{ $payment->date->format('d/m/Y') }}
                                    @if ($payment->expense_id)
                                        · <i class="bi bi-link-45deg"></i> movimiento registrado
                                    @else
                                        · sin movimiento asociado
                                    @endif
                                </div>
                                @if ($payment->notes)
                                    <div class="small text-muted fst-italic">{{ $payment->notes }}</div>
                                @endif
                            </div>
                            <form method="POST" action="{{ route('debts.payments.destroy', [$debt, $payment]) }}"
                                  onsubmit="return confirm('¿Eliminar este pago? El saldo volverá a subir y se borrará el movimiento asociado.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-icon text-danger" aria-label="Eliminar pago">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </div>
                    @empty
                        <p class="text-muted small mb-0 p-3">
                            Todavía no has registrado pagos de esta deuda.
                        </p>
                    @endforelse
                </div>
            </div>

            {{-- Refinanciaciones --}}
            @if ($refinancings->isNotEmpty())
                <div class="card border-0 mt-3">
                    <div class="card-header border-0 bg-transparent fw-semibold">
                        <i class="bi bi-arrow-repeat me-1"></i> Refinanciaciones
                    </div>
                    <div class="card-body p-0">
                        @foreach ($refinancings as $refinancing)
                            <div class="px-3 py-2 border-bottom">
                                <div class="d-flex justify-content-between gap-2">
                                    <span class="fw-semibold">@money($refinancing->refinanced_balance)</span>
                                    <span class="small text-muted">desde {{ $refinancing->start_date->format('d/m/Y') }}</span>
                                </div>
                                <div class="small text-muted">
                                    @if ($refinancing->interest_rate !== null)
                                        {{ str_replace('.', ',', rtrim(rtrim(number_format((float) $refinancing->interest_rate, 3, '.', ''), '0'), '.')) }} % anual
                                    @endif
                                    @if ($refinancing->term_months) · {{ $refinancing->term_months }} meses @endif
                                    @if ($refinancing->installment) · cuota @money($refinancing->installment) @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        {{-- Acciones --}}
        <div class="col-12 col-lg-5">
            {{-- Registrar pago --}}
            <div class="card border-0 mb-3">
                <div class="card-header border-0 bg-transparent fw-semibold">
                    <i class="bi bi-cash-coin me-1"></i> Registrar pago
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('debts.payments.store', $debt) }}">
                        @csrf
                        <div class="row g-2">
                            <div class="col-12">
                                <label for="pay_amount" class="form-label small fw-semibold">Monto</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" name="amount" id="pay_amount" required step="0.01" min="0.01"
                                           class="form-control @error('amount') is-invalid @enderror"
                                           value="{{ old('amount', $commitment > 0 ? $commitment : null) }}">
                                </div>
                                @error('amount')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-6">
                                <label for="pay_date" class="form-label small fw-semibold">Fecha</label>
                                <input type="date" name="date" id="pay_date" required
                                       max="{{ now(config('app.timezone'))->format('Y-m-d') }}"
                                       class="form-control @error('date') is-invalid @enderror"
                                       value="{{ old('date', now(config('app.timezone'))->format('Y-m-d')) }}">
                                @error('date')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-6">
                                <label for="pay_type" class="form-label small fw-semibold">Tipo</label>
                                <select name="type" id="pay_type" required class="form-select">
                                    @foreach (\App\Enums\DebtPaymentType::cases() as $case)
                                        <option value="{{ $case->value }}" @selected(old('type', 'scheduled') === $case->value)>
                                            {{ $case->label() }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12">
                                <label for="pay_account" class="form-label small fw-semibold">¿De qué cuenta salió?</label>
                                <select name="account_id" id="pay_account" class="form-select">
                                    <option value="">No registrar movimiento</option>
                                    @foreach ($accounts as $account)
                                        <option value="{{ $account->id }}" @selected((int) old('account_id', $debt->account_id) === $account->id)>
                                            {{ $account->name }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="form-text">Si eliges una cuenta, se registra el gasto y baja su saldo.</div>
                            </div>
                            <div class="col-12">
                                <label for="pay_category" class="form-label small fw-semibold">Categoría <span class="text-muted fw-normal">(opcional)</span></label>
                                <select name="category_id" id="pay_category" class="form-select">
                                    <option value="">Sin categoría</option>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category->id }}" @selected((int) old('category_id') === $category->id)>
                                            {{ $category->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12">
                                <label for="pay_notes" class="form-label small fw-semibold">Notas <span class="text-muted fw-normal">(opcional)</span></label>
                                <input type="text" name="notes" id="pay_notes" maxlength="2000"
                                       class="form-control" value="{{ old('notes') }}">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-finlia w-100 mt-3">Registrar pago</button>
                    </form>
                </div>
            </div>

            {{-- Refinanciar --}}
            <div class="card border-0 mb-3">
                <div class="card-header border-0 bg-transparent fw-semibold">
                    <i class="bi bi-arrow-repeat me-1"></i> Registrar refinanciación
                </div>
                <div class="card-body">
                    <p class="small text-muted">
                        Cambia las condiciones y fija un nuevo saldo de partida. Los pagos
                        anteriores quedan en el historial, pero ya no restan del nuevo saldo.
                    </p>
                    <form method="POST" action="{{ route('debts.refinancings.store', $debt) }}">
                        @csrf
                        <div class="row g-2">
                            <div class="col-12">
                                <label for="ref_balance" class="form-label small fw-semibold">Saldo refinanciado</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" name="refinanced_balance" id="ref_balance" required step="0.01" min="0.01"
                                           class="form-control @error('refinanced_balance') is-invalid @enderror"
                                           value="{{ old('refinanced_balance') }}">
                                </div>
                                @error('refinanced_balance')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-6">
                                <label for="ref_rate" class="form-label small fw-semibold">Nueva tasa (%)</label>
                                <input type="number" name="interest_rate" id="ref_rate" step="0.001" min="0"
                                       class="form-control" value="{{ old('interest_rate') }}">
                            </div>
                            <div class="col-6">
                                <label for="ref_term" class="form-label small fw-semibold">Plazo (meses)</label>
                                <input type="number" name="term_months" id="ref_term" min="1" max="600"
                                       class="form-control" value="{{ old('term_months') }}">
                            </div>
                            <div class="col-6">
                                <label for="ref_installment" class="form-label small fw-semibold">Nueva cuota</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" name="installment" id="ref_installment" step="0.01" min="0"
                                           class="form-control" value="{{ old('installment') }}">
                                </div>
                            </div>
                            <div class="col-6">
                                <label for="ref_start" class="form-label small fw-semibold">Desde</label>
                                <input type="date" name="start_date" id="ref_start" required
                                       class="form-control @error('start_date') is-invalid @enderror"
                                       value="{{ old('start_date', now(config('app.timezone'))->format('Y-m-d')) }}">
                                @error('start_date')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <button type="submit" class="btn btn-outline-finlia w-100 mt-3">Registrar refinanciación</button>
                    </form>
                </div>
            </div>

            {{-- Eliminar.
                 El nombre va con @js (Js::from), no con {{ }}: dentro de un
                 manejador en línea el navegador DECODIFICA las entidades HTML
                 antes de compilar el JS, así que un `&#039;` vuelve a ser una
                 comilla y escapa del literal. {{ }} protege HTML, no JS. --}}
            <form method="POST" action="{{ route('debts.destroy', $debt) }}"
                  onsubmit="return confirm('¿Eliminar la deuda «' + @js($debt->name) + '»?');">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-sm btn-outline-danger w-100">
                    <i class="bi bi-trash me-1"></i> Eliminar deuda
                </button>
            </form>
        </div>
    </div>

    {{-- Modal de edición --}}
    <div class="modal fade" id="editDebtModal" tabindex="-1" aria-labelledby="editDebtModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable">
            <div class="modal-content">
                <form method="POST" action="{{ route('debts.update', $debt) }}">
                    @csrf
                    @method('PUT')
                    <div class="modal-header">
                        <h2 class="modal-title h5" id="editDebtModalLabel">Editar deuda</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        @include('debts._form', ['debt' => $debt, 'prefix' => 'edit_'])
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-finlia">Guardar cambios</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
