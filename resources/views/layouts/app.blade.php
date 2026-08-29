<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'Panel' }} · Finlia</title>

    {{-- Identidad de marca (docs/BRAND.md): símbolo de puntos, sin cambios entre temas. --}}
    @include('layouts.partials.favicon')

    {{-- Anti-FOUC: fija el tema antes del primer paint --}}
    @include('layouts.partials.theme-head')

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="d-flex flex-column min-vh-100 @auth has-bottom-nav @endauth">

    {{-- ===================== Navbar (glass) ===================== --}}
    <nav class="navbar navbar-expand glass-nav sticky-top py-2">
        <div class="container-fluid">
            <div class="d-flex align-items-center gap-1">
                <a class="navbar-brand mb-0 d-flex align-items-center gap-2" href="{{ route('dashboard') }}">
                    <x-brandmark :size="26" />
                    <span>Finlia</span>
                </a>
            </div>

            @auth
                {{-- Selector de hogar activo --}}
                <div class="d-none d-md-block">
                    @include('layouts.partials.household-selector')
                </div>
            @endauth

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
        {{-- Sidebar: offcanvas en móvil (se abre desde la derecha, como el botón
             "Más" de la barra inferior que la activa), columna fija en escritorio --}}
        <aside class="offcanvas-lg offcanvas-end finlia-sidebar border-0"
               tabindex="-1" id="sidebar" aria-labelledby="sidebarLabel">
            <div class="offcanvas-header d-lg-none">
                <h5 class="offcanvas-title d-flex align-items-center gap-2" id="sidebarLabel">
                    <x-brandmark :size="22" /> Finlia
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#sidebar" aria-label="Cerrar"></button>
            </div>
            <div class="offcanvas-body py-3">
                <ul class="nav flex-column">
                    {{-- Panel, Movimientos y Presupuestos ya están en la barra inferior
                         móvil: mostrarlos también aquí sería duplicar destino. Solo
                         aparecen en el sidebar fijo de escritorio. --}}
                    <li class="d-none d-lg-block">
                        <a class="nav-link @if(request()->routeIs('dashboard'))active @endif" href="{{ route('dashboard') }}">
                            <i class="bi bi-speedometer2"></i> Panel
                        </a>
                    </li>

                    @auth
                        <li class="d-none d-lg-block">
                            <a class="nav-link @if(request()->routeIs('movements.*'))active @endif" href="{{ route('movements.index') }}">
                                <i class="bi bi-arrow-left-right"></i> Movimientos
                            </a>
                        </li>
                        <li class="d-none d-lg-block">
                            <a class="nav-link @if(request()->routeIs('budgets.*'))active @endif" href="{{ route('budgets.index') }}">
                                <i class="bi bi-cash-stack"></i> Presupuestos
                            </a>
                        </li>
                        <li>
                            <a class="nav-link @if(request()->routeIs('accounts.*'))active @endif" href="{{ route('accounts.index') }}">
                                <i class="bi bi-wallet"></i> Cuentas
                            </a>
                        </li>
                        <li>
                            <a class="nav-link @if(request()->routeIs('categories.*'))active @endif" href="{{ route('categories.index') }}">
                                <i class="bi bi-tags"></i> Categorías
                            </a>
                        </li>
                        <li>
                            <a class="nav-link @if(request()->routeIs('expected-incomes.*'))active @endif" href="{{ route('expected-incomes.index') }}">
                                <i class="bi bi-graph-up-arrow"></i> Ingresos esperados
                            </a>
                        </li>
                        <li>
                            <a class="nav-link @if(request()->routeIs('recurring-expenses.*'))active @endif" href="{{ route('recurring-expenses.index') }}">
                                <i class="bi bi-arrow-repeat"></i> Gastos recurrentes
                            </a>
                        </li>
                        <li>
                            <a class="nav-link @if(request()->routeIs('debts.*'))active @endif" href="{{ route('debts.index') }}">
                                <i class="bi bi-credit-card-2-front"></i> Deudas
                            </a>
                        </li>
                        <li>
                            <a class="nav-link @if(request()->routeIs('households.*'))active @endif" href="{{ route('households.index') }}">
                                <i class="bi bi-house-heart"></i> Hogares
                            </a>
                        </li>
                    @endauth

                    @php
                        // Marcadores de navegación de épicas futuras (deshabilitadas).
                        $proximos = [
                            ['icon' => 'bi-piggy-bank', 'label' => 'Metas de ahorro', 'epic' => 'Épica 7'],
                        ];
                    @endphp
                    @foreach ($proximos as $item)
                        <li class="d-none d-lg-block">
                            <span class="nav-link disabled" title="Disponible en la {{ $item['epic'] }}">
                                <i class="bi {{ $item['icon'] }}"></i> {{ $item['label'] }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </aside>

        {{-- Contenido principal.
             min-w-0: sin él, main (flex item) hereda min-width:auto y no puede
             encogerse por debajo del ancho intrínseco de su contenido, lo que
             provoca scroll horizontal en móvil. --}}
        <main class="flex-grow-1 min-w-0 p-3 p-md-4">
            @yield('content')
        </main>
    </div>

    {{-- ===================== Footer ===================== --}}
    <footer class="app-footer mt-auto py-3">
        <div class="container-fluid text-center small">
            Finlia · Finanzas familiares &middot;
            <span class="text-finlia fw-semibold">COP</span> &middot;
            v{{ config('finlia.version') }} &middot;
            &copy; {{ date('Y') }}
        </div>
    </footer>

    @auth
        @include('layouts.partials.mobile-bottom-nav')
    @endauth

    @stack('scripts')
</body>
</html>
