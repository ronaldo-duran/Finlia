<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'Acceso' }} · Finlia</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="d-flex flex-column min-vh-100">

    <main class="d-flex flex-grow-1 align-items-center justify-content-center py-5 px-3">
        <div class="w-100" style="max-width: 460px;">

            {{-- Marca --}}
            <div class="text-center mb-4">
                <a href="{{ route('home') }}" class="text-decoration-none d-inline-flex align-items-center gap-2">
                    <i class="bi bi-wallet2 fs-1 text-finlia"></i>
                    <span class="fs-3 fw-bold text-finlia">Finlia</span>
                </a>
                <p class="text-muted small mb-0">{{ $subtitle ?? '' }}</p>
            </div>

            {{-- Tarjeta del formulario --}}
            <div class="card shadow-sm border-0">
                <div class="card-body p-4">
                    @yield('content')
                </div>
            </div>

            @yield('actions')
        </div>
    </main>

    <footer class="footer py-3 text-center text-muted small">
        Finlia · Finanzas familiares &middot;
        <span class="text-finlia fw-semibold">COP</span>
    </footer>

    @stack('scripts')
</body>
</html>
