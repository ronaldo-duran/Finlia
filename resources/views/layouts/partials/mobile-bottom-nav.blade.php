{{--
    Barra inferior móvil: Panel, Movimientos, Presupuesto, Más.
    El FAB vive ahora FUERA de la barra (posición fija bottom-right,
    como el botón de componer de Twitter/X) para evitar el hueco
    visual en la barra y ganar presencia en toda la pantalla.
    Solo visible por debajo de lg.
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

{{-- FAB flotante — posición fija bottom-right, visible en móvil --}}
<div class="fab-container d-lg-none" id="fabContainer">
    {{-- Menú de acciones rápidas (oculto hasta que se abra) --}}
    <div class="fab-menu" id="fabMenu" aria-hidden="true">
        <a href="{{ route('savings-goals.index') }}" class="fab-action" tabindex="-1">
            <span class="fab-action-label">Aporte a meta</span>
            <div class="fab-action-icon" style="background:rgba(var(--finlia-primary-rgb),.85)">
                <i class="bi bi-piggy-bank"></i>
            </div>
        </a>
        <a href="{{ route('debts.index') }}" class="fab-action" tabindex="-1">
            <span class="fab-action-label">Pago de deuda</span>
            <div class="fab-action-icon" style="background:rgba(var(--finlia-primary-rgb),.85)">
                <i class="bi bi-credit-card-2-front"></i>
            </div>
        </a>
        <a href="{{ route('transfers.create') }}" class="fab-action" tabindex="-1">
            <span class="fab-action-label">Transferencia</span>
            <div class="fab-action-icon" style="background:rgba(var(--finlia-primary-rgb),.85)">
                <i class="bi bi-arrow-left-right"></i>
            </div>
        </a>
        <a href="{{ route('incomes.create') }}" class="fab-action" tabindex="-1">
            <span class="fab-action-label">Ingreso</span>
            <div class="fab-action-icon" style="background:rgba(var(--finlia-success-rgb),.9)">
                <i class="bi bi-plus-circle"></i>
            </div>
        </a>
        <a href="{{ route('expenses.create') }}" class="fab-action" tabindex="-1">
            <span class="fab-action-label">Gasto</span>
            <div class="fab-action-icon" style="background:rgba(var(--finlia-danger-rgb),.9)">
                <i class="bi bi-dash-circle"></i>
            </div>
        </a>
    </div>

    {{-- Botón principal --}}
    <button type="button" class="fab-btn" id="fabBtn"
            aria-label="Registrar movimiento" aria-expanded="false" aria-controls="fabMenu">
        <i class="bi bi-plus-lg" id="fabIcon"></i>
    </button>
</div>

{{-- Backdrop semitransparente al abrir el FAB --}}
<div class="fab-backdrop d-lg-none" id="fabBackdrop" aria-hidden="true"></div>

@push('scripts')
<script>
(function () {
    var container = document.getElementById('fabContainer');
    var btn       = document.getElementById('fabBtn');
    var menu      = document.getElementById('fabMenu');
    var backdrop  = document.getElementById('fabBackdrop');
    var icon      = document.getElementById('fabIcon');
    if (!btn || !menu || !backdrop) return;

    var open = false;

    function toggle() {
        open = !open;
        btn.setAttribute('aria-expanded', open);
        menu.setAttribute('aria-hidden', !open);
        container.classList.toggle('is-open', open);
        backdrop.classList.toggle('is-visible', open);
        icon.className = open ? 'bi bi-x-lg' : 'bi bi-plus-lg';
        menu.querySelectorAll('.fab-action').forEach(function (a) {
            a.setAttribute('tabindex', open ? '0' : '-1');
        });
    }

    btn.addEventListener('click', function (e) { e.stopPropagation(); toggle(); });
    backdrop.addEventListener('click', function () { if (open) toggle(); });
    document.addEventListener('keydown', function (e) { if (open && e.key === 'Escape') toggle(); });
})();
</script>
@endpush
