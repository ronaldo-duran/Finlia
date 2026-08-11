<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'Panel' }} · Finlia</title>

    {{-- Anti-FOUC: fija el tema antes del primer paint --}}
    @include('layouts.partials.theme-head')

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="d-flex flex-column min-vh-100">

    {{-- ===================== Navbar (glass) ===================== --}}
    <nav class="navbar navbar-expand glass-nav sticky-top py-2">
        <div class="container-fluid">
            <div class="d-flex align-items-center gap-1">
                {{-- Botón hamburguesa: abre el sidebar en móvil --}}
                <button class="btn-icon d-lg-none" type="button"
                        data-bs-toggle="offcanvas" data-bs-target="#sidebar" aria-controls="sidebar" aria-label="Abrir menú">
                    <i class="bi bi-list"></i>
                </button>
                <a class="navbar-brand mb-0 d-flex align-items-center gap-2" href="{{ route('dashboard') }}">
                    <i class="bi bi-wallet2 text-finlia"></i>
                    <span>Finlia</span>
                </a>
            </div>

            {{-- Acciones a la derecha --}}
            <ul class="navbar-nav flex-row align-items-center gap-1">
                @auth
                    {{-- Toggle de tema (claro/oscuro) --}}
                    <li class="nav-item">
                        <button type="button" class="btn-icon" data-theme-toggle aria-label="Cambiar tema">
                            <i class="bi bi-sun-fill show-in-dark"></i>
                            <i class="bi bi-moon-stars-fill show-in-light"></i>
                        </button>
                    </li>

                    {{-- Menú de usuario --}}
                    <li class="nav-item dropdown">
                        @php
                            $initials = collect(explode(' ', trim(Auth::user()->name)))
                                ->take(2)->map(fn($w) => strtoupper(substr($w, 0, 1)))->implode('');
                        @endphp
                        <button class="avatar-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Menú de usuario">
                            {{ $initials }}
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><h6 class="dropdown-header">{{ Auth::user()->name }}</h6></li>
                            <li><span class="dropdown-item-text small text-muted">{{ Auth::user()->email }}</span></li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="dropdown-item text-danger">
                                        <i class="bi bi-box-arrow-right me-1"></i> Cerrar sesión
                                    </button>
                                </form>
                            </li>
                        </ul>
                    </li>
                @endauth
            </ul>
        </div>
    </nav>

    {{-- ===================== Cuerpo: sidebar + contenido ===================== --}}
    <div class="d-flex flex-grow-1">
        {{-- Sidebar: offcanvas en móvil, columna fija en escritorio --}}
        <aside class="offcanvas-lg offcanvas-start finlia-sidebar border-0"
               tabindex="-1" id="sidebar" aria-labelledby="sidebarLabel">
            <div class="offcanvas-header d-lg-none">
                <h5 class="offcanvas-title" id="sidebarLabel">
                    <i class="bi bi-wallet2 me-1 text-finlia"></i> Finlia
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#sidebar" aria-label="Cerrar"></button>
            </div>
            <div class="offcanvas-body py-3">
                <ul class="nav flex-column">
                    <li>
                        <a class="nav-link @if(request()->routeIs('dashboard'))active @endif" href="{{ route('dashboard') }}">
                            <i class="bi bi-speedometer2"></i> Panel
                        </a>
                    </li>

                    @php
                        // Marcadores de navegación de épicas futuras (deshabilitadas).
                        $proximos = [
                            ['icon' => 'bi-house-door', 'label' => 'Hogares', 'epic' => 'Épica 2'],
                            ['icon' => 'bi-wallet', 'label' => 'Cuentas y movimientos', 'epic' => 'Épica 3'],
                            ['icon' => 'bi-cash-stack', 'label' => 'Presupuestos', 'epic' => 'Épica 4'],
                            ['icon' => 'bi-arrow-repeat', 'label' => 'Gastos recurrentes', 'epic' => 'Épica 5'],
                            ['icon' => 'bi-credit-card', 'label' => 'Deudas', 'epic' => 'Épica 6'],
                            ['icon' => 'bi-piggy-bank', 'label' => 'Metas de ahorro', 'epic' => 'Épica 7'],
                        ];
                    @endphp
                    @foreach ($proximos as $item)
                        <li>
                            <span class="nav-link disabled" title="Disponible en la {{ $item['epic'] }}">
                                <i class="bi {{ $item['icon'] }}"></i> {{ $item['label'] }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </aside>

        {{-- Contenido principal --}}
        <main class="flex-grow-1 p-3 p-md-4">
            @yield('content')
        </main>
    </div>

    {{-- ===================== Footer ===================== --}}
    <footer class="app-footer mt-auto py-3">
        <div class="container-fluid text-center small">
            Finlia · Finanzas familiares &middot;
            <span class="text-finlia fw-semibold">COP</span> &middot;
            &copy; {{ date('Y') }}
        </div>
    </footer>

    @stack('scripts')
</body>
</html>
