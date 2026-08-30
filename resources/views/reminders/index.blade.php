@extends('layouts.app', ['title' => 'Recordatorios'])

@php
    /**
     * Épica 9: recordatorios y notificaciones (ADR-0027).
     * $items     : lista unificada derivada (recurrentes, deudas, metas)
     *             + avisos sueltos, ordenada por fecha (vencidas primero).
     * $summary   : conteo {overdue, upcoming, attention, total}.
     * $completed : avisos sueltos ya atendidos (historial reciente).
     */
    $vencidas = $items->where('status', App\Enums\ReminderStatus::Overdue);
    $proximos = $items->where('status', App\Enums\ReminderStatus::Upcoming);
    $despues = $items->where('status', App\Enums\ReminderStatus::Pending);

    $groups = [
        ['label' => 'Vencidas', 'items' => $vencidas, 'icon' => 'bi-exclamation-octagon'],
        ['label' => 'Vencen pronto (7 días)', 'items' => $proximos, 'icon' => 'bi-bell'],
        ['label' => 'Más adelante', 'items' => $despues, 'icon' => 'bi-calendar3'],
    ];

    // Frecuencias con sentido para un aviso suelto (sin semanal/custom:
    // eso es un gasto recurrente de la Épica 5).
    $frequencies = collect([
        App\Enums\Frequency::Monthly,
        App\Enums\Frequency::Quarterly,
        App\Enums\Frequency::Semester,
        App\Enums\Frequency::Yearly,
    ])->mapWithKeys(fn ($f) => [$f->value => $f->label()])->all();
@endphp

@section('content')
    <x-flash-messages />

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-1">
        <h1 class="h3 mb-0"><i class="bi bi-bell me-2"></i>Recordatorios</h1>
        @if ($enabled)
            <span class="badge bg-finlia-subtle text-finlia border border-finlia rounded-pill px-3 py-2">
                {{ $summary['overdue'] }} vencidas · {{ $summary['upcoming'] }} próximas
            </span>
        @endif
    </div>
    <p class="text-muted mb-4">
        Todo lo que vence, en un solo sitio: gastos recurrentes, cuotas de deuda,
        metas con fecha y tus propios avisos (SOAT, tecnomecánica…).
        Se apagan <em>pagando</em>, no cerrando el aviso.
    </p>

    {{-- Interruptor del hogar (Épica 9: activar/desactivar recordatorios) --}}
    @if (! $enabled)
        <div class="alert alert-secondary d-flex gap-2 align-items-center" role="status">
            <i class="bi bi-bell-slash fs-5"></i>
            <div class="flex-grow-1">Los recordatorios de este hogar están <strong>desactivados</strong>.</div>
            @if ($canManageSettings)
                <form method="POST" action="{{ route('reminders.settings') }}">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="reminders_enabled" value="1">
                    <button type="submit" class="btn btn-sm btn-finlia">Activar</button>
                </form>
            @endif
        </div>
    @elseif ($canManageSettings)
        <div class="d-flex justify-content-end mb-3">
            <form method="POST" action="{{ route('reminders.settings') }}"
                  data-confirm="¿Desactivar los recordatorios del hogar? Nadie verá la campanita ni este listado.">
                @csrf
                @method('PUT')
                <input type="hidden" name="reminders_enabled" value="0">
                <button type="submit" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-bell-slash me-1"></i> Desactivar recordatorios
                </button>
            </form>
        </div>
    @endif

    @if ($enabled)
        <div class="row g-3">
            {{-- Columna: alta de aviso suelto --}}
            <div class="col-12 col-lg-4">
                <div class="card border-0">
                    <div class="card-header border-0 bg-transparent fw-semibold">
                        <i class="bi bi-plus-circle me-1"></i> Nuevo recordatorio
                    </div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('reminders.store') }}">
                            @csrf
                            <x-form-input label="De qué te recuerda" name="title" required
                                placeholder="Ej: Tecnomecánica, Renovar pasaporte" />

                            {{-- Input de dinero real con formato en vivo (UI_DESIGN §4). --}}
                            <div class="mb-3">
                                <label for="amount" class="form-label fw-semibold">Cuánto cuesta <span class="text-muted fw-normal">(opcional)</span></label>
                                <input id="amount" type="text" name="amount" inputmode="decimal"
                                    data-money-input placeholder="250000"
                                    class="form-control @error('amount') is-invalid @enderror"
                                    value="{{ old('amount') }}">
                                @error('amount')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>

                            <x-form-input label="Fecha límite" name="due_date" type="date" required
                                help="Puede ser una fecha pasada: el aviso queda como vencido." />

                            <x-form-select label="Se repite" name="frequency"
                                :options="$frequencies" placeholder="No, es de una sola vez" />

                            <details class="mb-3">
                                <summary class="small text-muted">Nota</summary>
                                <div class="pt-3">
                                    <textarea id="notes" name="notes" rows="2"
                                        class="form-control @error('notes') is-invalid @enderror"
                                        placeholder="Opcional">{{ old('notes') }}</textarea>
                                    @error('notes')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                </div>
                            </details>

                            <button type="submit" class="btn btn-finlia">
                                <i class="bi bi-check-lg me-1"></i> Añadir
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            {{-- Columna: lista unificada --}}
            <div class="col-12 col-lg-8">
                <div class="card border-0">
                    <div class="card-header border-0 bg-transparent fw-semibold">
                        <i class="bi bi-list-check me-1"></i> Próximas obligaciones
                    </div>

                    @if ($items->isEmpty())
                        <div class="card-body text-center text-muted py-5">
                            <i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>
                            Nada pendiente. Cuando algo venza —recurrente, cuota, meta o
                            un aviso propio— aparecerá aquí.
                        </div>
                    @else
                        <div class="list-group list-group-flush">
                            @foreach ($groups as $group)
                                @continue ($group['items']->isEmpty())
                                <div class="day-group-label">
                                    <i class="bi {{ $group['icon'] }} me-1"></i> {{ $group['label'] }}
                                </div>
                                @foreach ($group['items'] as $item)
                                    @php
                                        $days = $item['days_remaining'];
                                        [$badgeClass, $badgeText] = match ($item['status']) {
                                            App\Enums\ReminderStatus::Overdue => ['text-bg-danger', 'Vencida hace '.abs($days).' '.(abs($days) === 1 ? 'día' : 'días')],
                                            App\Enums\ReminderStatus::Upcoming => ['text-bg-warning', $days === 0 ? 'Vence hoy' : 'En '.$days.' '.($days === 1 ? 'día' : 'días')],
                                            App\Enums\ReminderStatus::Pending => ['text-bg-light text-muted', 'En '.$days.' días'],
                                            App\Enums\ReminderStatus::Completed => ['text-bg-success', 'Completado'],
                                        };
                                    @endphp
                                    <div class="list-group-item">
                                        <div class="d-flex justify-content-between align-items-center gap-2">
                                            <div class="min-w-0">
                                                <div class="fw-semibold text-truncate">
                                                    <i class="bi {{ $item['source']->icon() }} me-1 small"></i>{{ $item['title'] }}
                                                    <span class="badge rounded-pill {{ $badgeClass }}">{{ $badgeText }}</span>
                                                </div>
                                                <div class="small text-muted">
                                                    {{ $item['source']->label() }} ·
                                                    {{ $item['due_date']->format('d/m/Y') }}
                                                    @if ($item['amount'] !== null) · @money($item['amount']) @endif
                                                    @if ($item['detail']) · {{ $item['detail'] }} @endif
                                                </div>
                                            </div>
                                            <div class="d-flex gap-1 flex-shrink-0">
                                                {{-- La acción depende del origen: la vista enlaza,
                                                     el servicio no conoce rutas (ADR-0010). --}}
                                                @if ($item['source'] === App\Enums\ReminderSource::RecurringExpense)
                                                    <form method="POST" action="{{ route('recurring-expenses.mark-paid', $item['id']) }}"
                                                          data-confirm="¿Registrar el pago de «{{ $item['title'] }}» y avanzar la próxima fecha?">
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm btn-icon text-success"
                                                                aria-label="Marcar pagado" title="Marcar pagado">
                                                            <i class="bi bi-check2-circle"></i>
                                                        </button>
                                                    </form>
                                                @elseif ($item['source'] === App\Enums\ReminderSource::Debt)
                                                    <a href="{{ route('debts.show', $item['id']) }}" class="btn btn-sm btn-icon"
                                                       aria-label="Registrar pago de la deuda" title="Registrar pago">
                                                        <i class="bi bi-credit-card-2-front"></i>
                                                    </a>
                                                @elseif ($item['source'] === App\Enums\ReminderSource::SavingsGoal)
                                                    <a href="{{ route('savings-goals.show', $item['id']) }}" class="btn btn-sm btn-icon"
                                                       aria-label="Aportar a la meta" title="Aportar a la meta">
                                                        <i class="bi bi-piggy-bank"></i>
                                                    </a>
                                                @else
                                                    <form method="POST" action="{{ route('reminders.complete', $item['id']) }}"
                                                          data-confirm="¿Marcar «{{ $item['title'] }}» como atendido?">
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm btn-icon text-success"
                                                                aria-label="Atender" title="Atender">
                                                            <i class="bi bi-check2-circle"></i>
                                                        </button>
                                                    </form>
                                                    <button type="button" class="btn btn-sm btn-icon" aria-label="Editar"
                                                            data-bs-toggle="modal" data-bs-target="#editReminderModal"
                                                            data-id="{{ $item['id'] }}"
                                                            data-title="{{ $item['title'] }}"
                                                            data-amount="{{ $item['amount'] ?? '' }}"
                                                            data-next="{{ $item['due_date']->format('Y-m-d') }}"
                                                            data-frequency="{{ $item['frequency_value'] ?? '' }}"
                                                            data-notes="{{ $item['notes'] ?? '' }}">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <form method="POST" action="{{ route('reminders.destroy', $item['id']) }}"
                                                          data-confirm="¿Eliminar el recordatorio «{{ $item['title'] }}»?">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn btn-sm btn-icon text-danger" aria-label="Eliminar">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </form>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Historial de avisos sueltos atendidos --}}
        @if ($completed->isNotEmpty())
            <details class="card border-0 mt-3">
                <summary class="card-header border-0 bg-transparent fw-semibold">
                    <i class="bi bi-check2-all me-1"></i> Atendidos ({{ $completed->count() }})
                </summary>
                <div class="list-group list-group-flush">
                    @foreach ($completed as $done)
                        <div class="list-group-item d-flex justify-content-between align-items-center gap-2">
                            <div class="min-w-0">
                                <div class="fw-semibold text-truncate">
                                    {{ $done->title }}
                                    <span class="badge rounded-pill text-bg-success">Completado</span>
                                </div>
                                <div class="small text-muted">Vencía el {{ $done->due_date->format('d/m/Y') }}</div>
                            </div>
                            <form method="POST" action="{{ route('reminders.destroy', $done) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-icon text-danger" aria-label="Eliminar">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </div>
                    @endforeach
                </div>
            </details>
        @endif
    @endif

    {{-- Modal de edición del aviso suelto (relleno vía data-*). --}}
    <div class="modal fade" id="editReminderModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form id="editReminderForm" method="POST" action="">
                    @csrf
                    @method('PUT')
                    <div class="modal-header border-0">
                        <h5 class="modal-title"><i class="bi bi-pencil me-1"></i> Editar recordatorio</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <x-form-input label="De qué te recuerda" name="title" id="edit-rm-title" required />

                        <div class="mb-3">
                            <label for="edit-rm-amount" class="form-label fw-semibold">Cuánto cuesta <span class="text-muted fw-normal">(opcional)</span></label>
                            <input id="edit-rm-amount" type="text" name="amount" inputmode="decimal"
                                data-money-input placeholder="250000" class="form-control">
                        </div>

                        <x-form-input label="Fecha límite" name="due_date" type="date" id="edit-rm-due" required />

                        <x-form-select label="Se repite" name="frequency" id="edit-rm-frequency"
                            :options="$frequencies" placeholder="No, es de una sola vez" />

                        <div class="mb-3">
                            <label for="edit-rm-notes" class="form-label fw-semibold">Nota</label>
                            <textarea id="edit-rm-notes" name="notes" rows="2" class="form-control"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-finlia">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            // Rellena el modal de edición desde los data-* del botón (dato, no código).
            var modal = document.getElementById('editReminderModal');
            if (!modal) return;

            modal.addEventListener('show.bs.modal', function (event) {
                var btn = event.relatedTarget;
                if (!btn) return;

                var amount = btn.getAttribute('data-amount');
                document.getElementById('edit-rm-title').value = btn.getAttribute('data-title');
                document.getElementById('edit-rm-amount').value = amount
                    ? window.FinliaMoney.fromNumeric(amount) : '';
                document.getElementById('edit-rm-due').value = btn.getAttribute('data-next');
                document.getElementById('edit-rm-notes').value = btn.getAttribute('data-notes') || '';
                document.getElementById('edit-rm-frequency').value = btn.getAttribute('data-frequency') || '';

                document.getElementById('editReminderForm').action =
                    '{{ route('reminders.update', '__ID__') }}'.replace('__ID__', btn.getAttribute('data-id'));
            });
        })();
    </script>
@endpush
