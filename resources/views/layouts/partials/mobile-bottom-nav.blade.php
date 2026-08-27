{{--
    Barra inferior móvil (Épica 10, adelantado): Panel, Movimientos, botón
    central de registro, Presupuesto y Más (abre el sidebar completo desde
    la derecha, con las secciones que no caben en estas 4 pestañas). Solo
    visible por debajo de lg (el sidebar de escritorio cubre lo mismo).
--}}
<nav class="bottom-nav d-lg-none" aria-label="Navegación principal">
    <a href="{{ route('dashboard') }}"
       class="bottom-nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">
        <i class="bi {{ request()->routeIs('dashboard') ? 'bi-house-door-fill' : 'bi-house-door' }}"></i>
        <span>Panel</span>
    </a>
    <a href="{{ route('movements.index') }}"
       class="bottom-nav-item {{ request()->routeIs('movements.*') ? 'active' : '' }}">
        <i class="bi bi-arrow-left-right"></i>
        <span>Movimientos</span>
    </a>
    <div></div>
    <a href="{{ route('budgets.index') }}"
       class="bottom-nav-item {{ request()->routeIs('budgets.*') ? 'active' : '' }}">
        <i class="bi bi-cash-stack"></i>
        <span>Presupuesto</span>
    </a>
    <button type="button" class="bottom-nav-item" data-bs-toggle="offcanvas" data-bs-target="#sidebar"
            aria-controls="sidebar" aria-label="Más">
        <i class="bi bi-grid"></i>
        <span>Más</span>
    </button>
</nav>

<a href="{{ route('expenses.create') }}" class="bottom-nav-fab d-lg-none" aria-label="Registrar gasto">
    <i class="bi bi-plus-lg"></i>
</a>
