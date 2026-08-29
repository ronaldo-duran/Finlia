@extends('layouts.app', ['title' => 'Editar meta'])

@section('content')
    <x-flash-messages />

    <div class="row justify-content-center">
        <div class="col-12 col-lg-8 col-xl-7">
            <div class="d-flex align-items-center gap-2 mb-1">
                <a href="{{ route('savings-goals.show', $goal) }}" class="btn btn-icon" aria-label="Volver">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <h1 class="h4 mb-0"><i class="bi bi-pencil me-2"></i>Editar meta</h1>
            </div>

            <div class="card border-0 mt-3">
                <div class="card-body">
                    <form method="POST" action="{{ route('savings-goals.update', $goal) }}">
                        @csrf
                        @method('PUT')
                        @include('savings._form', ['goal' => $goal, 'prefix' => 'edit_'])

                        <div class="d-flex gap-2 mt-3">
                            <button type="submit" class="btn btn-finlia">
                                <i class="bi bi-check-lg me-1"></i> Guardar cambios
                            </button>
                            <a href="{{ route('savings-goals.show', $goal) }}" class="btn btn-outline-secondary">Cancelar</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
