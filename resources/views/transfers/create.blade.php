@extends('layouts.app', ['title' => 'Registrar transferencia'])

@section('content')
    <x-flash-messages />

    <div class="d-flex align-items-center justify-content-between gap-2 mb-4">
        <a href="{{ url()->previous() }}" class="btn btn-icon" aria-label="Volver">
            <i class="bi bi-arrow-left"></i>
        </a>
        <h1 class="h5 mb-0 fw-semibold">
            <i class="bi bi-arrow-left-right me-1"></i>Transferencia
        </h1>
        <span style="width: 40px;"></span>
    </div>

    <div class="row justify-content-center">
        <div class="col-12 col-lg-7">
            <div class="card border-0">
                <div class="card-body">
                    <form method="POST" action="{{ route('transfers.store') }}">
                        @csrf
                        @include('transfers._form')
                        <div class="d-flex gap-2 mt-4">
                            <button type="submit" class="btn btn-finlia btn-lg flex-fill">
                                <i class="bi bi-check-lg me-1"></i> Registrar transferencia
                            </button>
                            <a href="{{ route('movements.index') }}" class="btn btn-outline-secondary btn-lg">Cancelar</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
