{{--
    Componente: mensajes flash como toasts Bootstrap 5.
    Se auto-descartan a los 4,5 s; el usuario puede cerrarlos antes.
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
                class="toast align-items-center border-0 text-bg-{{ $toast['type'] }} shadow"
                role="alert"
                aria-atomic="true"
                data-bs-autohide="true"
                data-bs-delay="4500"
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
    (function () {
        document.querySelectorAll('.toast').forEach(function (el) {
            var t = bootstrap.Toast.getOrCreateInstance(el);
            t.show();
        });
    })();
    </script>
    @endpush
@endif
