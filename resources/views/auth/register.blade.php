@extends('layouts.guest', ['title' => 'Crear cuenta', 'subtitle' => 'Empieza a gestionar tus finanzas'])

@section('content')
    <x-flash-messages />

    <form method="POST" action="{{ route('register') }}">
        @csrf

        <x-form-input
            label="Nombre completo"
            name="name"
            :value="old('name')"
            autocomplete="name"
            autofocus
            required
            placeholder="Ej. María González"
        />

        <x-form-input
            label="Correo electrónico"
            name="email"
            type="email"
            :value="old('email')"
            autocomplete="username"
            required
            placeholder="tucorreo@ejemplo.com"
        />

        <x-form-input
            label="Fecha de nacimiento"
            name="birth_date"
            type="date"
            :value="old('birth_date')"
            :min="\Carbon\Carbon::create(1900, 1, 1)->toDateString()"
            :max="now()->subYears(18)->toDateString()"
            required
            help="Debes ser mayor de edad (18 años) para usar Finlia."
        />

        <x-form-input
            label="Contraseña"
            name="password"
            type="password"
            autocomplete="new-password"
            help="Mínimo 8 caracteres."
            required
        />

        <x-form-input
            label="Confirmar contraseña"
            name="password_confirmation"
            type="password"
            autocomplete="new-password"
            required
        />

        <div class="d-grid">
            <button type="submit" class="btn btn-finlia py-2">
                <i class="bi bi-person-plus me-1"></i> Crear cuenta
            </button>
        </div>
    </form>
@endsection

@section('actions')
    <div class="text-center mt-3 small">
        ¿Ya tienes cuenta?
        <a href="{{ route('login') }}" class="text-decoration-none fw-semibold text-finlia">Inicia sesión</a>
    </div>
@endsection
