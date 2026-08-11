@extends('layouts.guest', ['title' => 'Iniciar sesión', 'subtitle' => 'Tu dinero, bajo control'])

@section('content')
    <x-flash-messages />

    <form method="POST" action="{{ route('login') }}">
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

        <x-form-input
            label="Contraseña"
            name="password"
            type="password"
            autocomplete="current-password"
            required
        />

        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" name="remember" id="remember"
                   {{ $remember ? 'checked' : '' }}>
            <label class="form-check-label" for="remember">Recordarme en este dispositivo</label>
        </div>

        <div class="d-grid">
            <button type="submit" class="btn btn-finlia py-2">
                <i class="bi bi-box-arrow-in-right me-1"></i> Iniciar sesión
            </button>
        </div>
    </form>
@endsection

@section('actions')
    <div class="text-center mt-3 small">
        <a href="{{ route('password.request') }}" class="text-decoration-none">¿Olvidaste tu contraseña?</a>
        <div class="mt-2">
            ¿No tienes cuenta?
            <a href="{{ route('register') }}" class="text-decoration-none fw-semibold text-finlia">Regístrate</a>
        </div>
    </div>
@endsection
