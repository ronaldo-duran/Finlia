@extends('layouts.guest', ['title' => 'Nueva contraseña', 'subtitle' => 'Define una contraseña nueva'])

@section('content')
    <x-flash-messages />

    <form method="POST" action="{{ route('password.update') }}">
        @csrf

        {{-- Token de restablecimiento (oculto) --}}
        <input type="hidden" name="token" value="{{ $token }}">

        <x-form-input
            label="Correo electrónico"
            name="email"
            type="email"
            :value="old('email', $email)"
            autocomplete="username"
            autofocus
            required
        />

        <x-form-input
            label="Nueva contraseña"
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
                <i class="bi bi-shield-lock me-1"></i> Restablecer contraseña
            </button>
        </div>
    </form>
@endsection
