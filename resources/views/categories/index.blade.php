@extends('layouts.app', ['title' => 'Categorías'])

@section('content')
    <x-flash-messages />

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><i class="bi bi-tags me-2"></i>Categorías</h1>
    </div>

    <div class="row g-3">
        {{-- Columna: crear categoría --}}
        <div class="col-12 col-lg-4">
            <div class="card border-0">
                <div class="card-header border-0 bg-transparent fw-semibold">
                    <i class="bi bi-plus-circle me-1"></i> Nueva categoría
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('categories.store') }}">
                        @csrf
                        <x-form-input label="Nombre" name="name" required autofocus placeholder="Ej: Mascotas" />

                        <div class="row g-3">
                            <div class="col-6">
                                <x-form-select label="Tipo" name="type"
                                    :options="['expense' => 'Gasto', 'income' => 'Ingreso']"
                                    selected="expense" required />
                            </div>
                            <div class="col-6">
                                <label for="color" class="form-label fw-semibold">Color</label>
                                <input type="color" name="color" id="color" class="form-control form-control-color" value="{{ old('color', '#0b3f44') }}">
                            </div>
                        </div>

                        <button type="submit" class="btn btn-finlia mt-2">
                            <i class="bi bi-check-lg me-1"></i> Crear categoría
                        </button>
                    </form>
                </div>
            </div>
        </div>

        {{-- Columna: listado --}}
        <div class="col-12 col-lg-8">
            <div class="card border-0">
                <div class="card-header border-0 bg-transparent fw-semibold">
                    <i class="bi bi-list me-1"></i> Todas las categorías
                </div>
                @php
                    $byType = $categories->groupBy(fn ($c) => $c->type->value);
                @endphp
                @foreach (['expense' => 'Gastos', 'income' => 'Ingresos'] as $typeValue => $typeLabel)
                    @if (($byType[$typeValue] ?? collect())->isNotEmpty())
                        <h6 class="px-3 pt-3 text-muted text-uppercase small">{{ $typeLabel }}</h6>
                        <div class="list-group list-group-flush mb-2">
                            @foreach ($byType[$typeValue] as $category)
                                @php
                                    $isOwn = $category->household_id === $activeHouseholdId;
                                @endphp
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="color-dot" style="background-color: {{ $category->color ?: '#0b3f44' }}"></span>
                                        <div>
                                            <div class="fw-semibold">{{ $category->name }}</div>
                                            <span class="badge @if($isOwn) text-bg-light text-muted @else bg-finlia-subtle text-finlia @endif rounded-pill">
                                                {{ $isOwn ? 'Personal' : 'Global' }}
                                            </span>
                                        </div>
                                    </div>
                                    @if ($isOwn)
                                        <div class="d-flex gap-1">
                                            <button type="button" class="btn btn-sm btn-icon" aria-label="Editar"
                                                data-bs-toggle="modal" data-bs-target="#editCategoryModal"
                                                data-id="{{ $category->id }}"
                                                data-name="{{ $category->name }}"
                                                data-color="{{ $category->color ?: '#0b3f44' }}">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <form method="POST" action="{{ route('categories.destroy', $category) }}" data-confirm="¿Eliminar la categoría “{{ $category->name }}”?">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-icon text-danger" aria-label="Eliminar">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                @endforeach
            </div>
        </div>
    </div>

    {{-- Modal de edición (se rellena vía JS con data-*, sin interpolar input en código JS) --}}
    <div class="modal fade" id="editCategoryModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form id="editCategoryForm" method="POST" action="">
                    @csrf
                    @method('PUT')
                    <div class="modal-header border-0">
                        <h5 class="modal-title"><i class="bi bi-pencil me-1"></i> Editar categoría</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="category_id" id="edit-id">
                        <x-form-input label="Nombre" name="name" id="edit-name" required />
                        <label for="edit-color" class="form-label fw-semibold">Color</label>
                        <input type="color" name="color" id="edit-color" class="form-control form-control-color" value="#0b3f44">
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
        var modal = document.getElementById('editCategoryModal');
        if (!modal) return;
        modal.addEventListener('show.bs.modal', function (event) {
            var btn = event.relatedTarget;
            if (!btn) return;
            document.getElementById('edit-id').value = btn.getAttribute('data-id');
            document.getElementById('edit-name').value = btn.getAttribute('data-name');
            document.getElementById('edit-color').value = btn.getAttribute('data-color');
            document.getElementById('editCategoryForm').action =
                '{{ route('categories.update', '__ID__') }}'.replace('__ID__', btn.getAttribute('data-id'));
        });
    })();
</script>
@endpush
