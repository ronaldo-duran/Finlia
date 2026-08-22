@extends('layouts.app', ['title' => 'Nuevo presupuesto'])

@section('content')
    <x-flash-messages />

    <div class="row justify-content-center">
        <div class="col-12 col-lg-7 col-xl-6">
            <div class="d-flex align-items-center gap-2 mb-3">
                <a href="{{ route('budgets.index') }}" class="btn btn-icon" aria-label="Volver">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <h1 class="h4 mb-0"><i class="bi bi-cash-stack me-2"></i>Nuevo presupuesto</h1>
            </div>

            <div class="card border-0">
                <div class="card-body">
                    <form method="POST" action="{{ route('budgets.store') }}">
                        @include('budgets._form')

                        <div class="d-flex gap-2 mt-3">
                            <button type="submit" class="btn btn-finlia">
                                <i class="bi bi-check-lg me-1"></i> Guardar presupuesto
                            </button>
                            <a href="{{ route('budgets.index') }}" class="btn btn-outline-secondary">Cancelar</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
