@extends('layouts.app', ['title' => 'Invitación'])

@section('content')
    <x-flash-messages />

    <div class="row justify-content-center">
        <div class="col-12 col-md-8 col-lg-6">
            <div class="card border-0 shadow-sm text-center">
                <div class="card-body p-4">
                    <i class="bi bi-envelope-open-heart fs-1 text-finlia d-block mb-3"></i>
                    <h1 class="h4 mb-1">¡Tienes una invitación!</h1>
                    <p class="text-muted mb-4">
                        Te han invitado a unirte al hogar
                        <strong class="text-finlia">{{ $invitation->household->name }}</strong>
                        como <strong>{{ $invitation->role->label() }}</strong>.
                    </p>

                    <ul class="list-unstyled small text-muted mb-4">
                        <li><i class="bi bi-house me-1"></i> {{ $invitation->household->name }}</li>
                        <li><i class="bi bi-envelope me-1"></i> Invitación para: {{ $invitation->email }}</li>
                        <li><i class="bi bi-clock me-1"></i> Expira el {{ $invitation->expires_at->format('d/m/Y H:i') }}</li>
                    </ul>

                    @if ($invitation->status->value !== 'pending')
                        <div class="alert alert-warning border-0 mb-3" role="alert">
                            <i class="bi bi-exclamation-triangle me-1"></i>
                            Esta invitación ya no está disponible (estado: {{ $invitation->status->label() }}).
                        </div>
                    @elseif ($invitation->isExpired())
                        <div class="alert alert-warning border-0 mb-3" role="alert">
                            <i class="bi bi-clock-history me-1"></i>
                            Esta invitación ha expirado.
                        </div>
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
@endsection
