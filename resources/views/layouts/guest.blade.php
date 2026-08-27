<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'Acceso' }} · Finlia</title>

    {{-- Identidad de marca (docs/BRAND.md): símbolo de puntos, sin cambios entre temas. --}}
    @include('layouts.partials.favicon')

    {{-- Anti-FOUC: fija el tema antes del primer paint --}}
    @include('layouts.partials.theme-head')

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="d-flex flex-column min-vh-100">

    {{-- Toggle de tema flotante (pantallas sin navbar) --}}
    <button type="button" class="btn-icon glass theme-toggle-floating" data-theme-toggle aria-label="Cambiar tema">
        <i class="bi bi-sun-fill show-in-dark"></i>
        <i class="bi bi-moon-stars-fill show-in-light"></i>
    </button>

    <main class="d-flex flex-grow-1 align-items-center justify-content-center py-5 px-3">
        <div class="w-100" style="max-width: 460px;">

            {{-- Marca --}}
            <div class="text-center mb-4">
                <a href="{{ route('home') }}" class="text-decoration-none d-inline-flex align-items-center gap-2">
                    <x-brandmark :size="40" />
                    <span class="fs-3 fw-bold text-finlia">Finlia</span>
                </a>
                <p class="text-muted small mb-0">{{ $subtitle ?? '' }}</p>
            </div>

            {{-- Tarjeta del formulario (glass) --}}
            <div class="card glass-card border-0">
                <div class="card-body p-4">
                    @yield('content')
                </div>
            </div>

            @yield('actions')
        </div>
    </main>

    <footer class="app-footer py-3 text-center small">
        Finlia · Finanzas familiares &middot;
        <span class="text-finlia fw-semibold">COP</span>
    </footer>

    @stack('scripts')
</body>
</html>
