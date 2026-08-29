@extends('layouts.app', ['title' => 'Nueva meta de ahorro'])

@section('content')
    <x-flash-messages />

    <div class="row justify-content-center">
        <div class="col-12 col-lg-8 col-xl-7">
            <div class="d-flex align-items-center gap-2 mb-1">
                <a href="{{ route('savings-goals.index') }}" class="btn btn-icon" aria-label="Volver">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <h1 class="h4 mb-0"><i class="bi bi-piggy-bank me-2"></i>Nueva meta de ahorro</h1>
            </div>
            <p class="text-muted small ms-5 ps-2 mb-3">
                Convierte un objetivo en algo medible: cuánto, para cuándo y cuánto
                apartas cada mes.
            </p>

            <div class="card border-0">
                <div class="card-body">
                    <form method="POST" action="{{ route('savings-goals.store') }}">
                        @csrf
                        @include('savings._form', ['goal' => null, 'prefix' => 'new_'])

                        <div class="d-flex gap-2 mt-3">
                            <button type="submit" class="btn btn-finlia">
                                <i class="bi bi-check-lg me-1"></i> Crear meta
                            </button>
                            <a href="{{ route('savings-goals.index') }}" class="btn btn-outline-secondary">Cancelar</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
