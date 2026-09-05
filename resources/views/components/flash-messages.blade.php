{{--
    Componente: mensajes flash como toasts Bootstrap 5.
    Se auto-descartan solos; el usuario puede cerrarlos antes.

    La duración se ajusta a la longitud del texto (~16 caracteres/segundo,
    velocidad de lectura cómoda) acotada entre 4 y 7 segundos: un
    "Gasto registrado." no se queda estorbando, y un mensaje largo da
    tiempo a leerse entero.

    Uso: <x-flash-messages />
--}}
@php
    $toasts = [];
    if (session('status'))  { $toasts[] = ['type' => 'success', 'msg' => session('status')]; }
    if (session('success')) { $toasts[] = ['type' => 'success', 'msg' => session('success')]; }
    if (session('error'))   { $toasts[] = ['type' => 'danger',  'msg' => session('error')]; }
    foreach ((array) session('errors')?->getBag('default')->getMessages() as $messages) {
        foreach ((array) $messages as $message) {
            $toasts[] = ['type' => 'danger', 'msg' => $message];
        }
    }

    // 60 ms por carácter, con suelo de 4 s y techo de 7 s.
    $toastDelay = static fn (string $msg): int
        => max(4000, min(7000, mb_strlen($msg) * 60));
@endphp

@if (! empty($toasts))
    {{-- Contenedor fijo arriba-derecha, debajo de la navbar --}}
    <div
        class="toast-container position-fixed top-0 end-0 p-3"
        style="z-index: 1090; padding-top: calc(0.75rem + 56px) !important;"
        role="region"
        aria-live="polite"
        aria-label="Notificaciones"
    >
        @foreach ($toasts as $toast)
            <div
                class="toast show align-items-center border-0 text-bg-{{ $toast['type'] }} shadow"
                role="alert"
                aria-atomic="true"
                data-bs-autohide="true"
                data-bs-delay="{{ $toastDelay($toast['msg']) }}"
            >
                <div class="d-flex">
                    <div class="toast-body d-flex align-items-center gap-2">
                        @if ($toast['type'] === 'success')
                            <i class="bi bi-check-circle-fill flex-shrink-0"></i>
                        @else
                            <i class="bi bi-exclamation-triangle-fill flex-shrink-0"></i>
                        @endif
                        <span>{{ $toast['msg'] }}</span>
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto"
                            data-bs-dismiss="toast" aria-label="Cerrar"></button>
                </div>
            </div>
        @endforeach
    </div>

    @push('scripts')
    <script>
    // El toast ya se renderiza con la clase `show` (visible sin JS). Aquí solo
    // se engancha el auto-cierre. Se espera a DOMContentLoaded porque app.js es
    // un módulo diferido: antes de ese evento `window.bootstrap` aún no existe.
    document.addEventListener('DOMContentLoaded', function () {
        if (!window.bootstrap) return; // Sin JS de Bootstrap el aviso queda fijo: mejor eso que perderlo.

        document.querySelectorAll('.toast').forEach(function (el) {
            window.bootstrap.Toast.getOrCreateInstance(el).show();
        });
    });
    </script>
    @endpush
@endif
