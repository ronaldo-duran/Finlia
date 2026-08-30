@extends('layouts.guest', ['title' => 'Confirma tu correo', 'subtitle' => 'Un paso más'])

@section('content')
    <x-flash-messages />

    <div class="text-center mb-4">
        <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-finlia-subtle mb-3" style="width:64px; height:64px;">
            <i class="bi bi-envelope-check-fill fs-3 text-finlia"></i>
        </div>
        <h1 class="h4 fw-bold mb-2">Revisa tu correo</h1>
        <p class="text-muted small mb-0">
            Enviamos un enlace de confirmación a
            <strong>{{ auth()->user()->email }}</strong>.
            Ábrelo para activar tu cuenta.
        </p>
    </div>

    <div class="alert alert-light border small mb-4" role="note">
        <p class="mb-1"><i class="bi bi-search me-1"></i> ¿No llegó?</p>
        <ul class="mb-0 ps-3">
            <li>Revisa la carpeta de <strong>spam</strong> o promociones.</li>
            <li>El enlace vence en una hora; puedes pedir uno nuevo.</li>
        </ul>
    </div>

    <form method="POST" action="{{ route('verification.send') }}">
        @csrf
        <div class="d-grid">
            <button type="submit" class="btn btn-finlia py-2">
                <i class="bi bi-arrow-repeat me-1"></i> Reenviar enlace
            </button>
        </div>
    </form>
@endsection

@section('actions')
    <div class="text-center mt-3 small">
        <p class="mb-1 text-muted">
            ¿Te equivocaste al escribir el correo? Cierra sesión y vuelve a
            registrarte con el correo correcto.
        </p>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="btn btn-link btn-sm text-decoration-none p-0 text-finlia fw-semibold">
                Cerrar sesión
            </button>
        </form>
    </div>
@endsection
