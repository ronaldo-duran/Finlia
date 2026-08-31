@extends('layouts.guest', ['title' => 'Términos y condiciones', 'subtitle' => 'Un momento', 'width' => 720])

@section('content')
    <div class="text-center mb-4">
        <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-finlia-subtle mb-3" style="width:64px; height:64px;">
            <i class="bi bi-shield-check fs-3 text-finlia"></i>
        </div>
        <h1 class="h4 fw-bold mb-2">{{ $version->title }}</h1>
        <p class="text-muted small mb-0">
            Para seguir usando Finlia necesitas aceptar la versión
            <strong>{{ $version->version }}</strong>,
            {{ $lastAccepted ? 'actualizada' : 'vigente' }} el
            {{ $version->published_at->format('d/m/Y') }}.
        </p>
    </div>

    @if ($lastAccepted !== null)
        <div class="alert alert-light border small mb-4" role="note">
            <p class="mb-1">
                <i class="bi bi-arrow-repeat me-1"></i>
                Ya habías aceptado la versión {{ $lastAccepted->version }}:
                publicamos una nueva y hay que aceptarla de nuevo.
            </p>
            @if ($version->change_summary)
                <p class="mb-0"><strong>Qué cambió:</strong> {{ $version->change_summary }}</p>
            @endif
        </div>
    @endif

    {{-- Texto completo con scroll (no un enlace externo): lo que se acepta
         está en la propia pantalla. --}}
    <div class="border rounded-3 bg-body-tertiary p-3 mb-4" style="max-height: 45vh; overflow-y: auto;">
        @foreach (explode("\n\n", $version->content) as $parrafo)
            <p class="small mb-3">{{ $parrafo }}</p>
        @endforeach
    </div>

    {{-- Dos decisiones honestas, sin trampas oscuras: aceptar sigue; no
         aceptar lleva a una pantalla de salida que NO destruye nada. --}}
    <form method="POST">
        @csrf
        <div class="d-grid mb-2">
            <button type="submit" class="btn btn-finlia py-2" formaction="{{ route('terms.accept.store') }}">
                <i class="bi bi-check-lg me-1"></i> Aceptar y continuar
            </button>
        </div>
        <button type="submit" class="btn btn-link btn-sm text-muted text-decoration-none" formaction="{{ route('terms.reject') }}">
            No aceptar
        </button>
    </form>
@endsection
