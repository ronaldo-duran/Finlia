@extends('layouts.app', ['title' => 'Ingresos esperados'])

@section('content')
    <x-flash-messages />

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-1">
        <h1 class="h3 mb-0"><i class="bi bi-graph-up-arrow me-2"></i>Ingresos esperados</h1>
        <span class="badge bg-finlia-subtle text-finlia border border-finlia rounded-pill px-3 py-2">
            Total mensual: @money($monthlyTotal)
        </span>
    </div>
    <p class="text-muted mb-4">
        Lo que esperas recibir cada mes de forma fija: salario, arriendos, inversiones…
        Es la base del cálculo de <a href="{{ route('budgets.index') }}">cuánto puedes gastar</a>.
    </p>

    <div class="row g-3">
        {{-- Columna: alta --}}
        <div class="col-12 col-lg-4">
            <div class="card border-0">
                <div class="card-header border-0 bg-transparent fw-semibold">
                    <i class="bi bi-plus-circle me-1"></i> Nuevo ingreso esperado
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('expected-incomes.store') }}">
                        @csrf
                        <x-form-input label="Nombre" name="name" required placeholder="Ej: Salario" />
                        <x-form-input label="Monto mensual" name="amount" type="number" step="0.01" required
                            placeholder="3500000" />

                        <div class="row g-3">
                            <div class="col-6">
                                <x-form-input label="Día de cobro" name="day_of_month" type="number"
                                    placeholder="30" help="Opcional" />
                            </div>
                            <div class="col-6">
                                <x-form-select label="Categoría" name="category_id"
                                    :options="$categories" placeholder="Sin categoría" />
                            </div>
                        </div>

                        <input type="hidden" name="is_active" value="0">
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="is_active" value="1"
                                   id="is_active" checked>
                            <label class="form-check-label" for="is_active">
                                Contar en el cálculo de dinero disponible
                            </label>
                        </div>

                        <button type="submit" class="btn btn-finlia">
                            <i class="bi bi-check-lg me-1"></i> Añadir
                        </button>
                    </form>
                </div>
            </div>
        </div>

        {{-- Columna: listado --}}
        <div class="col-12 col-lg-8">
            <div class="card border-0">
                <div class="card-header border-0 bg-transparent fw-semibold">
                    <i class="bi bi-list me-1"></i> Configurados
                </div>

                @if ($expectedIncomes->isEmpty())
                    <div class="card-body text-center text-muted py-5">
                        <i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>
                        Aún no has configurado ningún ingreso esperado.
                    </div>
                @else
                    <div class="list-group list-group-flush">
                        @foreach ($expectedIncomes as $item)
                            <div class="list-group-item d-flex justify-content-between align-items-center gap-2">
                                <div class="min-w-0">
                                    <div class="fw-semibold text-truncate">
                                        {{ $item->name }}
                                        @unless ($item->is_active)
                                            <span class="badge rounded-pill text-bg-light text-muted">Inactivo</span>
                                        @endunless
                                    </div>
                                    <div class="small text-muted text-truncate">
                                        @money($item->amount)
                                        @if ($item->day_of_month) · día {{ $item->day_of_month }} @endif
                                        @if ($item->category) · {{ $item->category->name }} @endif
                                    </div>
                                </div>
                                <div class="d-flex gap-1">
                                    <button type="button" class="btn btn-sm btn-icon" aria-label="Editar"
                                            data-bs-toggle="modal" data-bs-target="#editExpectedIncomeModal"
                                            data-id="{{ $item->id }}"
                                            data-name="{{ $item->name }}"
                                            data-amount="{{ $item->amount }}"
                                            data-day="{{ $item->day_of_month }}"
                                            data-category="{{ $item->category_id }}"
                                            data-active="{{ $item->is_active ? '1' : '0' }}">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form method="POST" action="{{ route('expected-incomes.destroy', $item) }}"
                                          data-confirm="¿Eliminar «{{ $item->name }}»?">
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
        </div>
    </div>

    {{-- Modal de edición (se rellena vía JS con data-*, sin interpolar input en código JS) --}}
    <div class="modal fade" id="editExpectedIncomeModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form id="editExpectedIncomeForm" method="POST" action="">
                    @csrf
                    @method('PUT')
                    <div class="modal-header border-0">
                        <h5 class="modal-title"><i class="bi bi-pencil me-1"></i> Editar ingreso esperado</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <x-form-input label="Nombre" name="name" id="edit-ei-name" required />
                        <x-form-input label="Monto mensual" name="amount" type="number" step="0.01"
                            id="edit-ei-amount" required />
                        <div class="row g-3">
                            <div class="col-6">
                                <x-form-input label="Día de cobro" name="day_of_month" type="number" id="edit-ei-day" />
                            </div>
                            <div class="col-6">
                                <x-form-select label="Categoría" name="category_id" id="edit-ei-category"
                                    :options="$categories" placeholder="Sin categoría" />
                            </div>
                        </div>
                        <input type="hidden" name="is_active" value="0">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="edit-ei-active">
                            <label class="form-check-label" for="edit-ei-active">
                                Contar en el cálculo de dinero disponible
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
    // Rellena el modal de edición desde los data-* del botón (dato, no código).
    (function () {
        var modal = document.getElementById('editExpectedIncomeModal');
        if (!modal) return;
        modal.addEventListener('show.bs.modal', function (event) {
            var btn = event.relatedTarget;
            if (!btn) return;
            document.getElementById('edit-ei-name').value = btn.getAttribute('data-name');
            document.getElementById('edit-ei-amount').value = btn.getAttribute('data-amount');
            document.getElementById('edit-ei-day').value = btn.getAttribute('data-day') || '';
            document.getElementById('edit-ei-active').checked = btn.getAttribute('data-active') === '1';

            document.getElementById('edit-ei-category').value = btn.getAttribute('data-category') || '';

            document.getElementById('editExpectedIncomeForm').action =
                '{{ route('expected-incomes.update', '__ID__') }}'.replace('__ID__', btn.getAttribute('data-id'));
        });
    })();
</script>
@endpush
