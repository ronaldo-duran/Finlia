<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'Panel' }} · Finami</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="d-flex flex-column min-vh-100">

    {{-- ===================== Navbar ===================== --}}
    <nav class="navbar navbar-dark bg-finami shadow-sm sticky-top">
        <div class="container-fluid">
            <div class="d-flex align-items-center gap-2">
                {{-- Botón hamburguesa: abre el sidebar en móvil --}}
                <button class="btn btn-sm btn-outline-light d-lg-none" type="button"
                        data-bs-toggle="offcanvas" data-bs-target="#sidebar" aria-controls="sidebar" aria-label="Abrir menú">
                    <i class="bi bi-list fs-5"></i>
                </button>
                <a class="navbar-brand mb-0 d-flex align-items-center gap-2" href="{{ route('dashboard') }}">
                    <i class="bi bi-wallet2"></i>
                    <span class="fw-semibold">Finami</span>
                </a>
            </div>

            {{-- Menú de usuario --}}
            <ul class="navbar-nav flex-row align-items-center gap-2">
                @auth
                    <li class="nav-item dropdown">
                        <button class="btn btn-sm btn-outline-light dropdown-toggle d-flex align-items-center gap-2"
                                type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle"></i>
                            <span class="d-none d-sm-inline">{{ Auth::user()->name }}</span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow">
                            <li><h6 class="dropdown-header">{{ Auth::user()->email }}</h6></li>
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
        <aside class="offcanvas-lg offcanvas-start bg-finami text-white finami-sidebar border-0"
               tabindex="-1" id="sidebar" aria-labelledby="sidebarLabel">
            <div class="offcanvas-header d-lg-none">
                <h5 class="offcanvas-title" id="sidebarLabel">
                    <i class="bi bi-wallet2 me-1"></i> Finami
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" data-bs-target="#sidebar" aria-label="Cerrar"></button>
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
    <footer class="footer mt-auto py-3 bg-white border-top">
        <div class="container-fluid text-center text-muted small">
            Finami · Finanzas familiares &middot;
            <span class="text-finami fw-semibold">COP</span> &middot;
            &copy; {{ date('Y') }}
        </div>
    </footer>

    @stack('scripts')
</body>
</html>
