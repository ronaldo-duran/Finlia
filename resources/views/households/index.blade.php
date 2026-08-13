@extends('layouts.app', ['title' => 'Hogares'])

@section('content')
    <x-flash-messages />

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <div>
            <h1 class="h3 mb-1"><i class="bi bi-people-fill text-finlia me-1"></i> Tus hogares</h1>
            <p class="text-muted mb-0">Selecciona, crea o administra los hogares a los que perteneces.</p>
        </div>
        <a href="{{ route('households.create') }}" class="btn btn-finlia">
            <i class="bi bi-plus-lg me-1"></i> Crear hogar
        </a>
    </div>

    @if ($households->isEmpty())
        <div class="card border-0 shadow-sm text-center py-5">
            <div class="card-body">
                <i class="bi bi-house-heart fs-1 d-block mb-3 text-finlia opacity-75"></i>
                <h2 class="h5">Aún no tienes hogares</h2>
                <p class="text-muted">Crea tu primer hogar para empezar a organizar tus finanzas.</p>
                <a href="{{ route('households.create') }}" class="btn btn-finlia mt-1">
                    <i class="bi bi-plus-lg me-1"></i> Crear mi primer hogar
                </a>
            </div>
        </div>
    @else
        <div class="row g-3">
            @foreach ($households as $household)
                @php
                    $isActive = $household->id === $activeHouseholdId;
                    $isOwner = $household->owner_id === Auth::id();
                @endphp
                <div class="col-12 col-md-6 col-xl-4">
                    <div class="card h-100 border-0 shadow-sm @if ($isActive) border border-finlia @endif">
                        <div class="card-body d-flex flex-column">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <span class="badge bg-finlia-subtle text-finlia rounded-pill px-3 py-2">
                                    <i class="bi bi-person-badge me-1"></i>
                                    {{ $isOwner ? 'Administrador' : 'Miembro' }}
                                </span>
                                @if ($isActive)
                                    <span class="badge text-bg-success rounded-pill px-3 py-2">
                                        <i class="bi bi-check-circle-fill me-1"></i> Activo
                                    </span>
                                @endif
                            </div>
                            <h2 class="h5 mb-1">{{ $household->name }}</h2>
                            <p class="text-muted small mb-3">
                                <i class="bi bi-people me-1"></i>
                                {{ $household->members_count }} {{ Str::plural('miembro', (int) $household->members_count) }}
                                &middot; <i class="bi bi-currency-exchange"></i> {{ $household->currency }}
                            </p>

                            <div class="d-flex flex-wrap gap-2 mt-auto">
                                @if (! $isActive)
                                    <form method="POST" action="{{ route('households.activate', $household) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-finlia">
                                            <i class="bi bi-box-arrow-in-right me-1"></i> Activar
                                        </button>
                                    </form>
                                @endif
                                <a href="{{ route('households.show', $household) }}" class="btn btn-sm btn-outline-secondary">
                                    <i class="bi bi-gear me-1"></i> Administrar
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
@endsection
