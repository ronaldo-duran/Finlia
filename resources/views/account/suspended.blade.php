@extends('layouts.app', ['title' => 'Cuenta suspendida'])

@section('content')
    <div class="row justify-content-center mt-4">
        <div class="col-12 col-sm-10 col-md-8 col-lg-6">

            <div class="card border-0 text-center p-4">
                <div class="mb-3">
                    <i class="bi bi-hourglass-split text-warning" style="font-size:3rem;"></i>
                </div>
                <h1 class="h4 mb-2">Tu cuenta está suspendida</h1>
                <p class="text-muted mb-1">
                    Solicitaste eliminar tu cuenta.
                    Tienes hasta el <strong>{{ $deadline?->timezone(config('app.timezone'))->format('d/m/Y') }}</strong>
                    para arrepentirte.
                </p>
                <p class="text-muted mb-4">
                    Después de esa fecha todos tus datos se eliminarán de forma permanente.
                </p>

                <form method="POST" action="{{ route('account.reactivate') }}">
                    @csrf
                    <button type="submit" class="btn btn-finlia">
                        <i class="bi bi-arrow-counterclockwise me-1"></i> Reactivar mi cuenta
                    </button>
                </form>

                <div class="mt-4">
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="btn btn-link btn-sm text-muted">
                            Cerrar sesión
                        </button>
                    </form>
                </div>
            </div>

        </div>
    </div>
@endsection
