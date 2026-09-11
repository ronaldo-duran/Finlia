{{-- Pública (ADR-0039): sin sesión se ve con el layout de acceso y ofrece
     entrar o crear cuenta; con sesión, el botón de aceptar de siempre. --}}
@extends(auth()->check() ? 'layouts.app' : 'layouts.guest', [
    'title' => 'Invitación',
    'subtitle' => 'Te invitaron a un hogar',
])

@section('content')
    <x-flash-messages />

    @auth
        <div class="row justify-content-center">
            <div class="col-12 col-md-8 col-lg-6">
                <div class="card border-0 shadow-sm text-center">
                    <div class="card-body p-4">
                        @include('invitations._summary')

                        @if (! $acceptable)
                            @include('invitations._unavailable')
                            <a href="{{ route('dashboard') }}" class="btn btn-finlia">Ir al panel</a>
                        @elseif ($emailMismatch)
                            <div class="alert alert-info border-0 mb-3 text-start" role="alert">
                                <i class="bi bi-info-circle me-1"></i>
                                Esta invitación es para <strong>{{ $invitation->email }}</strong>, pero tu sesión actual es
                                <strong>{{ Auth::user()->email }}</strong>. Inicia sesión con el correo correcto para aceptarla.
                            </div>
                            <div class="d-flex justify-content-center gap-2">
                                <a href="{{ route('logout') }}" class="btn btn-outline-secondary" onclick="event.preventDefault(); document.getElementById('logout-form-invite').submit();">
                                    <i class="bi bi-box-arrow-right me-1"></i> Cerrar sesión
                                </a>
                                <a href="{{ route('dashboard') }}" class="btn btn-finlia">Ir al panel</a>
                            </div>
                            <form id="logout-form-invite" method="POST" action="{{ route('logout') }}" class="d-none">@csrf</form>
                        @else
                            <form method="POST" action="{{ route('invitations.accept', $token) }}">
                                @csrf
                                <button type="submit" class="btn btn-finlia btn-lg w-100 mb-2">
                                    <i class="bi bi-check-lg me-1"></i> Aceptar invitación
                                </button>
                            </form>
                            <a href="{{ route('dashboard') }}" class="btn btn-link btn-sm text-muted">Ahora no</a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @else
        <div class="text-center">
            @include('invitations._summary')

            {{-- Las dos salidas siempre, exista o no una cuenta con ese correo:
                 decirlo convertiría la invitación en un oráculo para averiguar
                 quién usa Finlia (docs/SECURITY.md, enumeración). --}}
            @if (! $acceptable)
                @include('invitations._unavailable')
                <p class="small text-muted mb-0">Pídele a quien te invitó que te envíe una nueva.</p>
            @else
                <a href="{{ route('register') }}" class="btn btn-finlia btn-lg w-100 mb-2">
                    <i class="bi bi-person-plus me-1"></i> Crear mi cuenta
                </a>
                <a href="{{ route('login') }}" class="btn btn-outline-finlia w-100">
                    Ya tengo cuenta, entrar
                </a>
            @endif
        </div>
    @endauth
@endsection
