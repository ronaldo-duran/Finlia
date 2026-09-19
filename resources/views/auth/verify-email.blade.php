@extends('layouts.guest', ['title' => 'Confirma tu correo', 'subtitle' => 'Un paso más'])

@section('content')
    <x-flash-messages />

    <div class="text-center mb-4">
        <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-finlia-subtle mb-3" style="width:64px; height:64px;">
            <i class="bi bi-envelope-check-fill fs-3 text-finlia"></i>
        </div>
        <h1 class="h4 fw-bold mb-2">Revisa tu correo</h1>
        <p class="text-muted small mb-0">
            Enviamos un enlace de confirmación a
            <strong>{{ auth()->user()->email }}</strong>.
            Ábrelo para activar tu cuenta.
        </p>
    </div>

    <div class="alert alert-light border small mb-4" role="note">
        <p class="mb-1"><i class="bi bi-search me-1"></i> ¿No llegó?</p>
        <ul class="mb-0 ps-3">
            <li>Revisa la carpeta de <strong>spam</strong> o promociones.</li>
            <li>El enlace vence en una hora; puedes pedir uno nuevo.</li>
            <li>Si lo abres en otro dispositivo, esta pantalla se actualizará sola.</li>
        </ul>
    </div>

    <form id="verified-check" method="GET" action="{{ route('verification.status') }}"
          data-verified-check
          data-dashboard-url="{{ route('dashboard') }}">
        <div class="d-grid mb-2">
            <button type="submit" class="btn btn-finlia py-2">
                <i class="bi bi-check2-circle me-1"></i> Ya verifiqué mi correo
            </button>
        </div>
        <div class="small text-muted text-center mb-3" data-verified-hint aria-live="polite">
            Comprobamos automáticamente cada pocos segundos.
        </div>
    </form>

    <form method="POST" action="{{ route('verification.send') }}">
        @csrf
        <div class="d-grid">
            <button type="submit" class="btn btn-outline-secondary py-2">
                <i class="bi bi-arrow-repeat me-1"></i> Reenviar enlace
            </button>
        </div>
    </form>
@endsection

@section('actions')
    <div class="text-center mt-3 small">
        <p class="mb-1 text-muted">
            ¿Te equivocaste al escribir el correo? Cierra sesión y vuelve a
            registrarte con el correo correcto.
        </p>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="btn btn-link btn-sm text-decoration-none p-0 text-finlia fw-semibold">
                Cerrar sesión
            </button>
        </form>
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            var form = document.querySelector('[data-verified-check]');
            if (!form) return;
            var statusUrl = form.getAttribute('action');
            var dashboardUrl = form.getAttribute('data-dashboard-url');
            var hint = form.querySelector('[data-verified-hint]');
            var stopped = false;

            function goToDashboard() {
                stopped = true;
                window.location.assign(dashboardUrl);
            }

            function check(manual) {
                return fetch(statusUrl, {
                    headers: { 'Accept': 'application/json' },
                    credentials: 'same-origin',
                    cache: 'no-store',
                })
                    .then(function (r) { return r.ok ? r.json() : null; })
                    .then(function (data) {
                        if (data && data.verified) {
                            goToDashboard();
                            return true;
                        }
                        if (manual && hint) {
                            hint.textContent = 'Aún no vemos la verificación. Abre el enlace desde tu correo y vuelve a pulsar.';
                        }
                        return false;
                    })
                    .catch(function () {
                        if (manual && hint) {
                            hint.textContent = 'No pudimos comprobar ahora. Revisa tu conexión y pulsa de nuevo.';
                        }
                        return false;
                    });
            }

            form.addEventListener('submit', function (e) {
                e.preventDefault();
                check(true);
            });

            function tick() {
                if (stopped) return;
                if (document.visibilityState === 'visible') {
                    check(false);
                }
                window.setTimeout(tick, 4000);
            }
            window.setTimeout(tick, 4000);

            document.addEventListener('visibilitychange', function () {
                if (!stopped && document.visibilityState === 'visible') check(false);
            });
        })();
    </script>
@endpush
