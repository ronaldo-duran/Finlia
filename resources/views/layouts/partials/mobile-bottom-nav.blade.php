{{--
    Barra inferior móvil del rediseño (Épica 10, adelantado): Panel,
    Movimientos, botón central de registro y dos accesos más que cambian
    de etiqueta según la variante de diseño (helper design_variant()).
    Solo visible por debajo de lg (el sidebar de escritorio cubre lo mismo).
--}}
@php
    $variant = design_variant();
@endphp

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
        <i class="bi {{ $variant === 'b' ? 'bi-pie-chart' : 'bi-cash-stack' }}"></i>
        <span>{{ $variant === 'b' ? 'Reportes' : 'Presupuesto' }}</span>
    </a>
    <button type="button" class="bottom-nav-item" data-bs-toggle="offcanvas" data-bs-target="#sidebar"
            aria-controls="sidebar" aria-label="{{ $variant === 'b' ? 'Perfil' : 'Más' }}">
        <i class="bi {{ $variant === 'b' ? 'bi-person' : 'bi-grid' }}"></i>
        <span>{{ $variant === 'b' ? 'Perfil' : 'Más' }}</span>
    </button>
</nav>

<a href="{{ route('expenses.create') }}" class="bottom-nav-fab d-lg-none" aria-label="Registrar gasto">
    <i class="bi bi-plus-lg"></i>
</a>
