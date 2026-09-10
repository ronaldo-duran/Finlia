@extends('layouts.guest', ['title' => $version->title, 'subtitle' => 'Términos y condiciones', 'width' => 720])

@section('content')
    @if ($historical)
        <div class="alert alert-light border small mb-4" role="note">
            <i class="bi bi-clock-history me-1"></i>
            Versión histórica <strong>{{ $version->version }}</strong>. La vigente
            está en <a href="{{ route('terms.show') }}" class="text-decoration-none">términos actuales</a>.
        </div>
    @endif

    <div class="text-center mb-4">
        <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-finlia-subtle mb-3" style="width:56px; height:56px;">
            <i class="bi bi-file-earmark-text fs-4 text-finlia"></i>
        </div>
        <h1 class="h4 fw-bold mb-1">{{ $version->title }}</h1>
        <p class="text-muted small mb-0">
            {{ $historical ? 'Publicada' : 'Vigente' }} desde el {{ $version->published_at->format('d/m/Y') }}
            · versión {{ $version->version }}
        </p>
    </div>

    {{-- Texto completo de la versión, párrafo a párrafo, siempre escapado. --}}
    <div class="text-body">
        @foreach (explode("\n\n", $version->content) as $parrafo)
            <p class="small mb-3">{{ $parrafo }}</p>
        @endforeach
    </div>

    <hr class="my-4">
    <p class="small text-muted text-center mb-0">
        <a href="{{ route('terms.history') }}" class="text-decoration-none">Ver todas las versiones</a>
        &middot;
        <a href="{{ route('data.policy') }}" class="text-decoration-none">Tus datos y Finlia</a>
    </p>
@endsection
