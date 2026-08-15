@extends('layouts.app', ['title' => 'Registrar gasto'])

@section('content')
    <x-flash-messages />

    <div class="d-flex align-items-center gap-2 mb-4">
        <a href="{{ url()->previous() }}" class="btn btn-icon" aria-label="Volver">
            <i class="bi bi-arrow-left"></i>
        </a>
        <h1 class="h3 mb-0"><i class="bi bi-dash-circle text-danger me-2"></i>Registrar gasto</h1>
    </div>

    <div class="row justify-content-center">
        <div class="col-12 col-lg-7">
            <div class="card border-0">
                <div class="card-body">
                    <form method="POST" action="{{ route('expenses.store') }}">
                        @csrf
                        @include('expenses._form')
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-finlia btn-lg">
                                <i class="bi bi-check-lg me-1"></i> Guardar gasto
                            </button>
                            <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary btn-lg">Cancelar</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
