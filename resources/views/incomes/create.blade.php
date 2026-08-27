@extends('layouts.app', ['title' => 'Registrar ingreso'])

@section('content')
    <x-flash-messages />

    <div class="d-flex align-items-center justify-content-between gap-2 mb-4">
        <a href="{{ url()->previous() }}" class="btn btn-icon" aria-label="Volver">
            <i class="bi bi-arrow-left"></i>
        </a>
        <div class="segmented">
            <a href="{{ route('expenses.create') }}" class="segmented-item">Gasto</a>
            <span class="segmented-item active">Ingreso</span>
        </div>
        <span style="width: 40px;"></span>
    </div>

    <div class="row justify-content-center">
        <div class="col-12 col-lg-7">
            <div class="card border-0">
                <div class="card-body">
                    <form method="POST" action="{{ route('incomes.store') }}">
                        @csrf
                        @include('incomes._form')
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-finlia btn-lg flex-fill">
                                <i class="bi bi-check-lg me-1"></i> Guardar ingreso
                            </button>
                            <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary btn-lg">Cancelar</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
