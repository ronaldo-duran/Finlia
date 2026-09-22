<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @auth
        <meta name="household-id" content="{{ active_household_id() ?? '' }}">
    @endauth
    <title>{{ $title ?? 'Panel' }} · Finlia</title>

    @include('layouts.partials.favicon')

    <link rel="manifest" href="/manifest.webmanifest">
    <meta name="theme-color" content="#eef3f8" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#0e1419" media="(prefers-color-scheme: dark)">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Finlia">

    @include('layouts.partials.theme-head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="d-flex flex-column min-vh-100 @auth has-bottom-nav @endauth">
    @include('layouts.partials.progress-bar')
    @auth
        @include('layouts.partials.ios-install-banner')
    @endauth
    <nav class="navbar navbar-expand glass-nav sticky-top py-2">
        <div class="container-fluid">
            <div class="d-flex align-items-center gap-1">
                <a class="navbar-brand mb-0 d-flex align-items-center gap-2" href="{{ route('dashboard') }}">
                    <x-brandmark :size="26" />
                    <span>Finlia</span>
                </a>
            </div>
            @auth
                <div class="d-none d-md-block">
                    @include('layouts.partials.household-selector')
                </div>
            @endauth
            <ul class="navbar-nav flex-row align-items-center gap-1">
                @auth

                    @include('layouts.partials.reminders-bell')
                    <li class="nav-item">
                        <button type="button" class="btn-icon" data-theme-toggle aria-label="Cambiar tema">
                            <i class="bi bi-sun-fill show-in-dark"></i>
                            <i class="bi bi-moon-stars-fill show-in-light"></i>
                        </button>
                    </li>
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
                                <a class="dropdown-item" href="{{ route('profile.edit') }}">
                                    <i class="bi bi-person me-1"></i> Mi perfil
                                </a>
                            </li>
                            @if (($finliaTour ?? null) && $finliaTour['payload'])
                                <li>
                                    <button type="button" class="dropdown-item" data-tour-open>
                                        <i class="bi bi-compass me-1"></i>
                                        Guía de esta pantalla
                                    </button>
                                </li>
                            @endif
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
    <div class="d-flex flex-grow-1">
        <aside class="offcanvas-lg offcanvas-end finlia-sidebar border-0"
               tabindex="-1" id="sidebar" aria-labelledby="sidebarLabel">
            <div class="offcanvas-header d-lg-none">
                <h5 class="offcanvas-title d-flex align-items-center gap-2" id="sidebarLabel">
                    <x-brandmark :size="22" /> Finlia
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#sidebar" aria-label="Cerrar"></button>
            </div>
            <div class="offcanvas-body py-3 d-flex flex-column">
                <ul class="nav flex-column">
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
                            <a class="nav-link @if(request()->routeIs('reports.*'))active @endif" href="{{ route('reports.index') }}">
                                <i class="bi bi-bar-chart-line"></i> Reportes
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
                            <a class="nav-link @if(request()->routeIs('receivables.*'))active @endif" href="{{ route('receivables.index') }}">
                                <i class="bi bi-inbox-fill"></i> Cuentas por cobrar
                            </a>
                        </li>
                        <li>
                            <a class="nav-link @if(request()->routeIs('savings-goals.*'))active @endif" href="{{ route('savings-goals.index') }}">
                                <i class="bi bi-piggy-bank"></i> Metas de ahorro
                            </a>
                        </li>
                        <li>
                            <a class="nav-link @if(request()->routeIs('reminders.*'))active @endif" href="{{ route('reminders.index') }}">
                                <i class="bi bi-bell"></i> Recordatorios
                            </a>
                        </li>
                        <li>
                            <a class="nav-link @if(request()->routeIs('households.*'))active @endif" href="{{ route('households.index') }}">
                                <i class="bi bi-house-heart"></i> Hogares
                            </a>
                        </li>
                        <li><hr class="my-2 opacity-25"></li>
                        <li>
                            <a class="nav-link @if(request()->routeIs('terms.*'))active @endif" href="{{ route('terms.show') }}">
                                <i class="bi bi-file-text"></i> Términos
                            </a>
                        </li>
                        <li>
                            <a class="nav-link @if(request()->routeIs('data.policy'))active @endif" href="{{ route('data.policy') }}">
                                <i class="bi bi-shield-check"></i> Tus datos
                            </a>
                        </li>
                        <li>
                            <a class="nav-link @if(request()->routeIs('bug-report.*'))active @endif" href="{{ route('bug-report.create') }}">
                                <i class="bi bi-bug"></i> Reportar un error
                            </a>
                        </li>
                    @endauth
                </ul>
                @auth
                    <p class="small text-body-tertiary text-center mt-auto pt-3 mb-0" data-testid="app-version">
                        Finlia v{{ config('finlia.version') }}
                    </p>
                @endauth
            </div>
        </aside>
        <main class="flex-grow-1 min-w-0 p-3 p-md-4">
            @yield('content')
        </main>
    </div>
    <footer class="app-footer mt-auto py-3">
        <div class="container-fluid text-center small">
            Finlia · Finanzas familiares &middot;
            <span class="text-finlia fw-semibold">COP</span> &middot;
            <a href="{{ route('terms.show') }}" class="text-decoration-none">Términos</a> &middot;
            <a href="{{ route('data.policy') }}" class="text-decoration-none">Tus datos</a> &middot;
            <a href="{{ route('bug-report.create') }}" class="text-decoration-none">Reportar un error</a> &middot;
            v{{ config('finlia.version') }} &middot;
            &copy; {{ date('Y') }}
        </div>
    </footer>
    @auth
        @include('layouts.partials.mobile-bottom-nav')
        @include('layouts.partials.fab')
        @include('layouts.partials.tour')
        @include('layouts.partials.compulsive-survey-modal')
    @endauth
    <div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header border-0 pb-1">
                    <h5 class="modal-title fw-semibold" id="confirmModalLabel">
                        <i class="bi bi-exclamation-circle me-1 text-warning"></i>
                        Confirmar acción
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body" id="confirmModalBody">
                    ¿Estás seguro?
                </div>
                <div class="modal-footer border-0 pt-1">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">
                        Cancelar
                    </button>
                    <button type="button" class="btn btn-sm btn-danger" id="confirmModalOk">
                        Sí, continuar
                    </button>
                </div>
            </div>
        </div>
    </div>
    <script>
    (function () {
        var modalEl     = document.getElementById('confirmModal');
        var bodyEl      = document.getElementById('confirmModalBody');
        var okEl        = document.getElementById('confirmModalOk');
        var modal       = null;
        var pendingForm = null;
        function getModal() {
            if (!modal) modal = window.bootstrap.Modal.getOrCreateInstance(modalEl);
            return modal;
        }
        function reallySubmit(form) {
            HTMLFormElement.prototype.submit.call(form);
        }
        document.addEventListener('submit', function (e) {
            var form = e.target.closest && e.target.closest('form[data-confirm]');
            if (!form) return;
            var msg = form.getAttribute('data-confirm') || '¿Estás seguro?';
            if (!window.bootstrap) {
                if (!window.confirm(msg)) e.preventDefault();
                return;
            }
            e.preventDefault();
            bodyEl.textContent = msg;
            pendingForm = form;
            getModal().show();
        }, true);
        okEl.addEventListener('click', function () {
            var form = pendingForm;
            pendingForm = null;
            getModal().hide();
            if (!form) return;
            if (window.Finlia && window.Finlia.cargando) {
                window.Finlia.cargando.ocuparFormulario(form);
            }

            reallySubmit(form);
        });
        modalEl.addEventListener('hidden.bs.modal', function () {
            pendingForm = null;
        });
    })();
    </script>
    @stack('modals')
    @stack('scripts')
</body>
</html>
