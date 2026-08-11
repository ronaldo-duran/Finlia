{{--
    Componente: mensajes flash.
    Rendera éxito / error / estado de sesión como alertas Bootstrap 5.
    Uso: <x-flash-messages />
--}}
@php
    $alerts = [];
    if (session('status')) { $alerts['success'] = session('status'); }
    if (session('success')) { $alerts['success'] = session('success'); }
    if (session('error')) { $alerts['danger'] = session('error'); }
    // Errores de validación no ligados a un campo concreto (p. ej. login fallido).
    foreach ((array) session('errors')?->getBag('default')->getMessages() as $messages) {
        foreach ((array) $messages as $message) {
            $alerts['danger'] = $message;
        }
    }
@endphp

@if (! empty($alerts))
    <div class="flash-messages" role="region" aria-live="polite" aria-label="Mensajes">
        @foreach ($alerts as $type => $message)
            <div class="alert alert-{{ $type }} alert-dismissible fade show shadow-sm" role="alert">
                @if ($type === 'success')
                    <i class="bi bi-check-circle-fill me-1"></i>
                @elseif ($type === 'danger')
                    <i class="bi bi-exclamation-triangle-fill me-1"></i>
                @endif
                <span>{{ $message }}</span>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
            </div>
        @endforeach
    </div>
@endif
