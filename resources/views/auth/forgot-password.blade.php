@extends('layouts.guest', ['title' => 'Recuperar contraseña', 'subtitle' => 'Te enviaremos un enlace para restablecerla'])

@section('content')
    <x-flash-messages />

    <p class="text-muted small mb-3">
        Ingresa tu correo y te enviaremos un enlace para crear una contraseña nueva.
    </p>

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <x-form-input
            label="Correo electrónico"
            name="email"
            type="email"
            :value="old('email')"
            autocomplete="username"
            autofocus
            required
        />

        <div class="d-grid">
            <button type="submit" class="btn btn-finlia py-2">
                <i class="bi bi-envelope-paper me-1"></i> Enviar enlace
            </button>
        </div>
    </form>
@endsection

@section('actions')
    <div class="text-center mt-3 small">
        <a href="{{ route('login') }}" class="text-decoration-none">
            <i class="bi bi-arrow-left"></i> Volver a iniciar sesión
        </a>
    </div>
@endsection
