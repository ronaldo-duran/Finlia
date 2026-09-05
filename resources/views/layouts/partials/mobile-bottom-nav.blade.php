{{--
    Barra inferior móvil (Épica 10): Panel, Movimientos, botón central de
    registro (FAB speed-dial), Presupuesto y Más (sidebar offcanvas desde
    la derecha). Solo visible por debajo de lg.

    El FAB abre un menú vertical de acciones rápidas (speed-dial) en lugar
    de navegar directamente a /gastos/crear. Esto evita un paso cuando el
    usuario quiere registrar un ingreso, una transferencia, un aporte a meta
    o un pago de deuda. La animación y el fondo translúcido son CSS/JS puro.
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
    {{-- Hueco para el FAB --}}
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

{{-- FAB speed-dial: botón central + menú vertical de acciones rápidas --}}
<div class="fab-speed-dial d-lg-none" id="fabSpeedDial">
    {{-- Menú de opciones (oculto hasta que se abra) --}}
    <div class="fab-speed-dial-menu" id="fabMenu" aria-hidden="true">
        <a href="{{ route('savings-goals.index') }}" class="fab-option" tabindex="-1">
            <span class="fab-option-label">Aporte a meta</span>
            <div class="fab-option-btn" style="background-color: rgba(var(--finlia-primary-rgb),.85);">
                <i class="bi bi-piggy-bank"></i>
            </div>
        </a>
        <a href="{{ route('debts.index') }}" class="fab-option" tabindex="-1">
            <span class="fab-option-label">Pago de deuda</span>
            <div class="fab-option-btn" style="background-color: rgba(var(--finlia-primary-rgb),.85);">
                <i class="bi bi-credit-card-2-front"></i>
            </div>
        </a>
        <a href="{{ route('transfers.create') }}" class="fab-option" tabindex="-1">
            <span class="fab-option-label">Transferencia</span>
            <div class="fab-option-btn" style="background-color: rgba(var(--finlia-primary-rgb),.85);">
                <i class="bi bi-arrow-left-right"></i>
            </div>
        </a>
        <a href="{{ route('incomes.create') }}" class="fab-option" tabindex="-1">
            <span class="fab-option-label">Ingreso</span>
            <div class="fab-option-btn" style="background-color: rgba(var(--finlia-success-rgb),.9);">
                <i class="bi bi-plus-circle"></i>
            </div>
        </a>
        <a href="{{ route('expenses.create') }}" class="fab-option" tabindex="-1">
            <span class="fab-option-label">Gasto</span>
            <div class="fab-option-btn" style="background-color: rgba(var(--finlia-danger-rgb),.9);">
                <i class="bi bi-dash-circle"></i>
            </div>
        </a>
    </div>

    {{-- Botón principal ("+") --}}
    <button type="button" class="bottom-nav-fab" id="fabBtn"
            aria-label="Registrar" aria-expanded="false" aria-controls="fabMenu">
        <i class="bi bi-plus-lg" id="fabIcon"></i>
    </button>
</div>

{{-- Fondo oscuro al abrir el FAB --}}
<div class="fab-backdrop d-lg-none" id="fabBackdrop" aria-hidden="true"></div>

@push('scripts')
<script>
(function () {
    var dial = document.getElementById('fabSpeedDial');
    var btn = document.getElementById('fabBtn');
    var menu = document.getElementById('fabMenu');
    var backdrop = document.getElementById('fabBackdrop');
    var icon = document.getElementById('fabIcon');
    if (!btn || !menu || !backdrop) return;

    var open = false;

    function toggle() {
        open = !open;
        btn.setAttribute('aria-expanded', open);
        menu.setAttribute('aria-hidden', !open);
        dial.classList.toggle('is-open', open);
        backdrop.classList.toggle('is-visible', open);
        // Rotamos el "+" a "×" para indicar que se cierra.
        icon.className = open ? 'bi bi-x-lg' : 'bi bi-plus-lg';
        // Accesibilidad: focus-trap ligero (activar/desactivar tabindex).
        menu.querySelectorAll('.fab-option').forEach(function (a) {
            a.setAttribute('tabindex', open ? '0' : '-1');
        });
    }

    btn.addEventListener('click', function (e) {
        e.stopPropagation();
        toggle();
    });

    backdrop.addEventListener('click', function () {
        if (open) toggle();
    });

    document.addEventListener('keydown', function (e) {
        if (open && e.key === 'Escape') toggle();
    });
})();
</script>
@endpush
