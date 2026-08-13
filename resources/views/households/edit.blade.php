@extends('layouts.app', ['title' => 'Editar hogar'])

@section('content')
    <x-flash-messages />

    <div class="row justify-content-center">
        <div class="col-12 col-lg-8 col-xl-7">
            <div class="d-flex align-items-center gap-2 mb-4">
                <a href="{{ route('households.show', $household) }}" class="btn-icon" aria-label="Volver">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <h1 class="h3 mb-0"><i class="bi bi-pencil-square text-finlia me-1"></i> Editar hogar</h1>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-body p-3 p-md-4">
                    <form method="POST" action="{{ route('households.update', $household) }}">
                        @csrf
                        @method('PUT')
                        @include('households._form', ['household' => $household])

                        <div class="d-flex gap-2 mt-2">
                            <button type="submit" class="btn btn-finlia">
                                <i class="bi bi-check-lg me-1"></i> Guardar cambios
                            </button>
                            <a href="{{ route('households.show', $household) }}" class="btn btn-outline-secondary">Cancelar</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
