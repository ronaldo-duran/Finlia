@extends('layouts.app', ['title' => 'Gastos recurrentes'])

@php
    /**
     * Épica 5: gastos recurrentes y obligaciones futuras.
     * $upcoming : activos enriquecidos por RecurringExpenseService (días,
     *            ahorro mensual…), ya ordenados por próxima fecha.
     * $all      : modelos completos (para el modal de edición y los pausados).
     */
    $byId = $all->keyBy('id');
    $vencidas = $upcoming->where('is_overdue');
    $estaSemana = $upcoming->where('is_overdue', false)->where('days_remaining', '<=', 7);
    $despues = $upcoming->where('days_remaining', '>', 7);
    $pausados = $all->where('is_active', false);

    // Grupos de la sección "Próximas obligaciones" (solo los no vacíos).
    $groups = [
        ['label' => 'Vencidas', 'items' => $vencidas, 'icon' => 'bi-exclamation-octagon'],
        ['label' => 'Vencen esta semana', 'items' => $estaSemana, 'icon' => 'bi-bell'],
        ['label' => 'Más adelante', 'items' => $despues, 'icon' => 'bi-calendar3'],
    ];
@endphp

@section('content')
    <x-flash-messages />

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-1">
        <h1 class="h3 mb-0"><i class="bi bi-arrow-repeat me-2"></i>Gastos recurrentes</h1>
        <span class="badge bg-finlia-subtle text-finlia border border-finlia rounded-pill px-3 py-2">
            Separa @money($totalMonthlySavings)/mes
        </span>
    </div>
    <p class="text-muted mb-4">
        Lo que sabes que va a llegar: arriendo, servicios, SOAT, suscripciones…
        Aquí no se registran pagos, se <em>planifican</em>: alimentan el cálculo de
        <a href="{{ route('budgets.index') }}">cuánto puedes gastar</a>.
    </p>

    {{-- Avisos (el detalle completo vive en "Próximas obligaciones") --}}
    @if ($vencidas->isNotEmpty())
        <div class="alert alert-danger d-flex gap-2" role="alert">
            <i class="bi bi-exclamation-octagon-fill fs-5"></i>
            <div>
                <strong>Obligaciones vencidas</strong>:
                {{ $vencidas->map(fn ($r) => $r['name'])->join(', ', ' y ') }}.
                Márcalas como pagadas para volver a cuadrar tu dinero disponible.
            </div>
        </div>
    @elseif ($estaSemana->isNotEmpty())
        <div class="alert alert-warning d-flex gap-2" role="alert">
            <i class="bi bi-bell-fill fs-5"></i>
            <div>
                {{ $estaSemana->map(fn ($r) => $r['name'].' vence '.$r['days_remaining'].' día'.($r['days_remaining'] === 1 ? '' : 's'))->join('; ', ' y ') }}.
            </div>
        </div>
    @endif

    <div class="row g-3">
        {{-- Columna: alta --}}
        <div class="col-12 col-lg-4">
            <div class="card border-0">
                <div class="card-header border-0 bg-transparent fw-semibold">
                    <i class="bi bi-plus-circle me-1"></i> Nueva obligación
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('recurring-expenses.store') }}">
                        @csrf
                        <x-form-input label="Nombre" name="name" required placeholder="Ej: SOAT, Arriendo" />

                        {{-- Input de dinero real con formato en vivo (UI_DESIGN §4):
                             x-form-input no propaga data-*, va como HTML crudo. --}}
                        <div class="mb-3">
                            <label for="amount" class="form-label fw-semibold">
                                Monto estimado <span class="text-danger" aria-hidden="true">*</span>
                            </label>
                            <input id="amount" type="text" name="amount" inputmode="decimal"
                                data-money-input placeholder="600000" required
                                class="form-control @error('amount') is-invalid @enderror"
                                value="{{ old('amount') }}">
                            @error('amount')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                            <div class="form-text">Monto en COP por cada pago. Usa la coma para decimales.</div>
                        </div>

                        <x-form-select label="Frecuencia" name="frequency" required
                            :options="App\Enums\Frequency::options()" placeholder="Selecciona…" />

                        <div id="interval-wrapper" class="d-none">
                            <x-form-input label="Cada cuántos días" name="frequency_interval" type="number"
                                placeholder="45" help="Solo para frecuencia personalizada (1 a 3650 días)." />
                        </div>

                        <x-form-input label="Próxima fecha de pago" name="next_date" type="date" required
                            help="Puede ser una fecha pasada: la obligación queda marcada como vencida." />

                        <details class="mb-3">
                            <summary class="small text-muted">Cuenta y notas</summary>
                            <div class="pt-3">
                                <x-form-select label="Cuenta con la que se paga" name="account_id"
                                    :options="$accounts" placeholder="Sin cuenta asociada"
                                    help="Con cuenta, «Marcar pagado» registra el gasto por ti." />
                                <div class="mb-3">
                                    <label for="notes" class="form-label fw-semibold">Notas</label>
                                    <textarea id="notes" name="notes" rows="2"
                                        class="form-control @error('notes') is-invalid @enderror"
                                        placeholder="Opcional">{{ old('notes') }}</textarea>
                                    @error('notes')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </details>

                        <div class="mb-3">
                            <x-form-select label="Categoría" name="category_id"
                                :options="$categories" placeholder="Sin categoría" />
                        </div>

                        <input type="hidden" name="is_active" value="0">
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="is_active" value="1"
                                   id="is_active" checked>
                            <label class="form-check-label" for="is_active">
                                Contar en el dinero disponible y avisarme
                            </label>
                        </div>
                        <input type="hidden" name="auto_generate" value="0">
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="auto_generate" value="1"
                                   id="auto_generate">
                            <label class="form-check-label" for="auto_generate">
                                Registrar el pago solo cuando llegue la fecha
                            </label>
                            <div class="form-text">
                                Al vencer, el pago se registra automáticamente de madrugada
                                (requiere el programa diario del servidor; en desarrollo local no corre solo).
                            </div>
                        </div>

                        <button type="submit" class="btn btn-finlia">
                            <i class="bi bi-check-lg me-1"></i> Añadir
                        </button>
                    </form>
                </div>
            </div>
        </div>

        {{-- Columna: próximas obligaciones --}}
        <div class="col-12 col-lg-8">
            <div class="card border-0">
                <div class="card-header border-0 bg-transparent fw-semibold">
                    <i class="bi bi-calendar-event me-1"></i> Próximas obligaciones
                </div>

                @if ($upcoming->isEmpty())
                    <div class="card-body text-center text-muted py-5">
                        <i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>
                        Nada pendiente. Añade tus gastos fijos y obligaciones para
                        que dejen de tomarte por sorpresa.
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
                                    [$badgeClass, $badgeText] = match (true) {
                                        $days < 0 => ['text-bg-danger', 'Vencida hace '.abs($days).' '.(abs($days) === 1 ? 'día' : 'días')],
                                        $days === 0 => ['text-bg-warning', 'Vence hoy'],
                                        $days <= 7 => ['text-bg-warning', 'En '.$days.' '.(abs($days) === 1 ? 'día' : 'días')],
                                        default => ['text-bg-light text-muted', 'En '.$days.' días'],
                                    };
                                    $edit = $byId[$item['id']];
                                @endphp
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-center gap-2">
                                        <div class="min-w-0">
                                            <div class="fw-semibold text-truncate">
                                                {{ $item['name'] }}
                                                <span class="badge rounded-pill {{ $badgeClass }}">{{ $badgeText }}</span>
                                            </div>
                                            <div class="small text-muted">
                                                {{ $item['next_date']->format('d/m/Y') }} ·
                                                {{ $item['frequency_label'] }} · @money($item['amount'])
                                                @if ($item['category_name']) · {{ $item['category_name'] }} @endif
                                            </div>
                                            @if ($item['frequency'] !== App\Enums\Frequency::Monthly)
                                                <div class="small text-finlia-accent">
                                                    <i class="bi bi-piggy-bank me-1"></i>Separa ~@money($item['monthly_savings']) al mes
                                                </div>
                                            @endif
                                        </div>
                                        <div class="d-flex gap-1 flex-shrink-0">
                                            <form method="POST" action="{{ route('recurring-expenses.mark-paid', $item['id']) }}"
                                                  data-confirm="¿Registrar el pago de «{{ $item['name'] }}» (@money($item['amount'])) y avanzar la próxima fecha?">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-icon text-success"
                                                        aria-label="Marcar pagado" title="Marcar pagado">
                                                    <i class="bi bi-check2-circle"></i>
                                                </button>
                                            </form>
                                            <button type="button" class="btn btn-sm btn-icon" aria-label="Editar"
                                                    data-bs-toggle="modal" data-bs-target="#editRecurringModal"
                                                    data-id="{{ $edit->id }}"
                                                    data-name="{{ $edit->name }}"
                                                    data-amount="{{ $edit->amount }}"
                                                    data-frequency="{{ $edit->frequency->value }}"
                                                    data-interval="{{ $edit->frequency_interval }}"
                                                    data-next="{{ $edit->next_date->format('Y-m-d') }}"
                                                    data-category="{{ $edit->category_id }}"
                                                    data-account="{{ $edit->account_id }}"
                                                    data-active="{{ $edit->is_active ? '1' : '0' }}"
                                                    data-auto="{{ $edit->auto_generate ? '1' : '0' }}"
                                                    data-notes="{{ $edit->notes }}">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <form method="POST" action="{{ route('recurring-expenses.destroy', $edit) }}"
                                                  data-confirm="¿Eliminar «{{ $edit->name }}»?">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-icon text-danger" aria-label="Eliminar">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
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

    {{-- Pausados: no cuentan para el cálculo ni generan avisos --}}
    @if ($pausados->isNotEmpty())
        <div class="card border-0 mt-3">
            <div class="card-header border-0 bg-transparent fw-semibold">
                <i class="bi bi-pause-circle me-1"></i> Pausados
            </div>
            <div class="list-group list-group-flush">
                @foreach ($pausados as $edit)
                    <div class="list-group-item d-flex justify-content-between align-items-center gap-2">
                        <div class="min-w-0">
                            <div class="fw-semibold text-truncate">{{ $edit->name }}</div>
                            <div class="small text-muted text-truncate">
                                {{ $edit->frequency->shortLabel($edit->frequency_interval) }} · @money($edit->amount)
                            </div>
                        </div>
                        <div class="d-flex gap-1">
                            <button type="button" class="btn btn-sm btn-icon" aria-label="Editar"
                                    data-bs-toggle="modal" data-bs-target="#editRecurringModal"
                                    data-id="{{ $edit->id }}"
                                    data-name="{{ $edit->name }}"
                                    data-amount="{{ $edit->amount }}"
                                    data-frequency="{{ $edit->frequency->value }}"
                                    data-interval="{{ $edit->frequency_interval }}"
                                    data-next="{{ $edit->next_date->format('Y-m-d') }}"
                                    data-category="{{ $edit->category_id }}"
                                    data-account="{{ $edit->account_id }}"
                                    data-active="{{ $edit->is_active ? '1' : '0' }}"
                                    data-auto="{{ $edit->auto_generate ? '1' : '0' }}"
                                    data-notes="{{ $edit->notes }}">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <form method="POST" action="{{ route('recurring-expenses.destroy', $edit) }}"
                                  data-confirm="¿Eliminar «{{ $edit->name }}»?">
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
        </div>
    @endif

    {{-- Modal de edición (se rellena vía JS con data-*, sin interpolar input en código JS) --}}
    <div class="modal fade" id="editRecurringModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form id="editRecurringForm" method="POST" action="">
                    @csrf
                    @method('PUT')
                    <div class="modal-header border-0">
                        <h5 class="modal-title"><i class="bi bi-pencil me-1"></i> Editar obligación</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <x-form-input label="Nombre" name="name" id="edit-re-name" required />

                        {{-- Input de dinero real con formato en vivo (UI_DESIGN §4). --}}
                        <div class="mb-3">
                            <label for="edit-re-amount" class="form-label fw-semibold">
                                Monto estimado <span class="text-danger" aria-hidden="true">*</span>
                            </label>
                            <input id="edit-re-amount" type="text" name="amount" inputmode="decimal"
                                data-money-input placeholder="600000" required
                                class="form-control @error('amount') is-invalid @enderror">
                            @error('amount')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <x-form-select label="Frecuencia" name="frequency" id="edit-re-frequency" required
                            :options="App\Enums\Frequency::options()" placeholder="Selecciona…" />

                        <div id="edit-interval-wrapper" class="d-none">
                            <x-form-input label="Cada cuántos días" name="frequency_interval" type="number"
                                id="edit-re-interval" help="Solo para frecuencia personalizada." />
                        </div>

                        <x-form-input label="Próxima fecha de pago" name="next_date" type="date"
                            id="edit-re-next" required />

                        <x-form-select label="Categoría" name="category_id" id="edit-re-category"
                            :options="$categories" placeholder="Sin categoría" />
                        <x-form-select label="Cuenta con la que se paga" name="account_id" id="edit-re-account"
                            :options="$accounts" placeholder="Sin cuenta asociada" />

                        <div class="mb-3">
                            <label for="edit-re-notes" class="form-label fw-semibold">Notas</label>
                            <textarea id="edit-re-notes" name="notes" rows="2" class="form-control"></textarea>
                        </div>

                        <input type="hidden" name="is_active" value="0">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="edit-re-active">
                            <label class="form-check-label" for="edit-re-active">
                                Contar en el dinero disponible y avisarme
                            </label>
                        </div>
                        <input type="hidden" name="auto_generate" value="0">
                        <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" name="auto_generate" value="1" id="edit-re-auto">
                            <label class="form-check-label" for="edit-re-auto">
                                Registrar el pago solo cuando llegue la fecha
                            </label>
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
            // El intervalo "cada N días" solo aplica a la frecuencia personalizada:
            // se muestra/oculta en el alta y en el modal, en los dos sentidos.
            function syncInterval(selectId, wrapperId) {
                var select = document.getElementById(selectId);
                var wrapper = document.getElementById(wrapperId);
                if (!select || !wrapper) return;

                var update = function () {
                    wrapper.classList.toggle('d-none', select.value !== 'custom');
                };
                select.addEventListener('change', update);
                update();
            }

            syncInterval('frequency', 'interval-wrapper');
            syncInterval('edit-re-frequency', 'edit-interval-wrapper');

            // Rellena el modal de edición desde los data-* del botón (dato, no código).
            var modal = document.getElementById('editRecurringModal');
            if (!modal) return;
            modal.addEventListener('show.bs.modal', function (event) {
                var btn = event.relatedTarget;
                if (!btn) return;

                document.getElementById('edit-re-name').value = btn.getAttribute('data-name');
                document.getElementById('edit-re-amount').value =
                    window.FinliaMoney.fromNumeric(btn.getAttribute('data-amount'));
                document.getElementById('edit-re-next').value = btn.getAttribute('data-next');
                document.getElementById('edit-re-interval').value = btn.getAttribute('data-interval') || '';
                document.getElementById('edit-re-notes').value = btn.getAttribute('data-notes') || '';
                document.getElementById('edit-re-active').checked = btn.getAttribute('data-active') === '1';
                document.getElementById('edit-re-auto').checked = btn.getAttribute('data-auto') === '1';

                var frequency = document.getElementById('edit-re-frequency');
                frequency.value = btn.getAttribute('data-frequency') || '';
                frequency.dispatchEvent(new Event('change'));

                document.getElementById('edit-re-category').value = btn.getAttribute('data-category') || '';
                document.getElementById('edit-re-account').value = btn.getAttribute('data-account') || '';

                document.getElementById('editRecurringForm').action =
                    '{{ route('recurring-expenses.update', '__ID__') }}'.replace('__ID__', btn.getAttribute('data-id'));
            });
        })();
    </script>
@endpush
