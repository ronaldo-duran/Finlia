{{-- Navegación del sitio público. Los enlaces de producto llevan al host de
     la app: route('login')/route('register') resuelven con APP_URL. --}}
<a href="#contenido" class="visually-hidden-focusable saltar-contenido">Saltar al contenido</a>

<header class="marketing-nav glass-nav">
    <nav class="container d-flex align-items-center justify-content-between gap-3" aria-label="Principal">
        <a href="{{ route('home') }}" class="marca d-inline-flex align-items-center gap-2 text-decoration-none">
            <x-brandmark :size="30" />
            <span class="fs-5 fw-semibold">Finlia</span>
        </a>

        <div class="d-none d-md-flex align-items-center gap-4">
            <a href="#como-funciona" class="enlace-nav">Cómo funciona</a>
            <a href="#funciones" class="enlace-nav">Funciones</a>
            <a href="#preguntas" class="enlace-nav">Preguntas</a>
        </div>

        <div class="d-flex align-items-center gap-2">
            <button type="button" class="btn-icon" data-theme-toggle aria-label="Cambiar tema">
                <i class="bi bi-sun-fill show-in-dark"></i>
                <i class="bi bi-moon-stars-fill show-in-light"></i>
            </button>
            <a href="{{ route('login') }}" class="btn btn-outline-finlia btn-sm d-none d-sm-inline-flex">Entrar</a>
            <a href="{{ route('register') }}" class="btn btn-finlia btn-sm">Crear cuenta</a>
        </div>
    </nav>
</header>
