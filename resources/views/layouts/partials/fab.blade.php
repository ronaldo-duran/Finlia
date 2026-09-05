{{--
    FAB flotante (botón de acción principal) con speed-dial de 5 acciones.

    Vive fuera de la barra inferior y se muestra en TODOS los tamaños:
    en móvil se apoya sobre la barra; en escritorio queda abajo a la derecha.

    Se oculta en pantallas de formulario (`*.create` / `*.edit`): ahí sería
    redundante — ya estás registrando algo — y taparía los botones de acción
    del propio formulario (Guardar / Cancelar).
--}}
@php
    $hideFab = request()->routeIs('*.create', '*.edit');
@endphp

@unless ($hideFab)
    <div class="fab-container" id="fabContainer">
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
    <div class="fab-backdrop" id="fabBackdrop" aria-hidden="true"></div>

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

        // Ocultar al bajar, mostrar al subir. Así el FAB nunca se queda
        // tapando un botón de la página: basta un gesto mínimo para apartarlo.
        var lastY  = window.scrollY;
        var ticking = false;

        function onScroll() {
            var y = window.scrollY;

            // Con el menú abierto no se mueve (el backdrop bloquea el scroll de fondo).
            if (open) { lastY = y; return; }

            // Umbral pequeño para ignorar el rebote elástico y el temblor del dedo.
            if (Math.abs(y - lastY) < 8) return;

            container.classList.toggle('is-hidden', y > lastY && y > 90);
            lastY = y;
        }

        window.addEventListener('scroll', function () {
            if (ticking) return;
            ticking = true;
            window.requestAnimationFrame(function () { onScroll(); ticking = false; });
        }, { passive: true });
    })();
    </script>
    @endpush
@endunless
