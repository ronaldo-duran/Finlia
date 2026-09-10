@extends('layouts.guest', ['title' => 'Historial de términos', 'subtitle' => 'Versiones publicadas', 'width' => 720])

@section('content')
    <div class="text-center mb-4">
        <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-finlia-subtle mb-3"
             style="width:56px; height:56px;">
            <i class="bi bi-clock-history fs-4 text-finlia"></i>
        </div>
        <h1 class="h4 fw-bold mb-1">Historial de términos</h1>
        <p class="text-muted small mb-0">
            Cada versión publicada, con su fecha. Ninguna se edita después de publicarse.
        </p>
    </div>

    <div class="list-group list-group-flush">
        @foreach ($versions as $version)
            @php
                $esVigente = $current !== null && $version->is($current);
                $aceptadaEl = $accepted[$version->getKey()] ?? null;
            @endphp

            <a href="{{ route('terms.version', $version) }}"
               class="list-group-item list-group-item-action px-0 py-3 bg-transparent">
                <div class="d-flex justify-content-between align-items-start gap-3">
                    <div class="min-w-0">
                        <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                            <span class="fw-semibold">{{ $version->title }}</span>
                            @if ($esVigente)
                                <span class="badge text-bg-success">Vigente</span>
                            @endif
                        </div>

                        <div class="small text-muted">
                            Versión {{ $version->version }} ·
                            publicada el {{ $version->published_at->format('d/m/Y') }}
                        </div>

                        @if ($version->change_summary)
                            <div class="small text-body mt-1">{{ $version->change_summary }}</div>
                        @endif

                        {{-- La prueba de consentimiento es del usuario: si la
                             aceptó, tiene derecho a ver cuándo. --}}
                        @if ($aceptadaEl)
                            <div class="small text-finlia mt-1">
                                <i class="bi bi-check-circle-fill"></i>
                                La aceptaste el {{ $aceptadaEl->format('d/m/Y') }}
                            </div>
                        @endif
                    </div>

                    <i class="bi bi-chevron-right text-muted flex-shrink-0 mt-1"></i>
                </div>
            </a>
        @endforeach
    </div>

    <hr class="my-4">
    <p class="small text-muted text-center mb-0">
        <a href="{{ route('terms.show') }}" class="text-decoration-none">Términos vigentes</a>
        &middot;
        <a href="{{ route('data.policy') }}" class="text-decoration-none">Tus datos</a>
    </p>
@endsection
