@php
    $households = Auth::user()->households()->orderByPivot('joined_at')->get();
    $active = active_household();
@endphp

<div class="dropdown">
    <button class="btn btn-sm household-selector-btn d-flex align-items-center gap-2" type="button"
            data-bs-toggle="dropdown" aria-expanded="false"
            aria-label="Cambiar de hogar">
        <i class="bi bi-people-fill text-finlia"></i>
        <span class="text-truncate" style="max-width: 150px;">
            {{ $active?->name ?? 'Sin hogar' }}
        </span>
        <i class="bi bi-chevron-down small"></i>
    </button>
    <ul class="dropdown-menu dropdown-menu-start shadow-sm">
        <li><h6 class="dropdown-header">Tus hogares</h6></li>
        @forelse ($households as $household)
            <li>
                <form method="POST" action="{{ route('households.activate', $household) }}">
                    @csrf
                    <button type="submit" class="dropdown-item d-flex justify-content-between align-items-center">
                        <span class="text-truncate">{{ $household->name }}</span>
                        @if ($active?->is($household))
                            <i class="bi bi-check-circle-fill text-finlia ms-2"></i>
                        @endif
                    </button>
                </form>
            </li>
        @empty
            <li><span class="dropdown-item-text text-muted">Aún no tienes hogares.</span></li>
        @endforelse
        <li><hr class="dropdown-divider"></li>
        <li>
            <a class="dropdown-item" href="{{ route('households.index') }}">
                <i class="bi bi-list me-1"></i> Ver todos
            </a>
        </li>
        <li>
            <a class="dropdown-item" href="{{ route('households.create') }}">
                <i class="bi bi-plus-lg me-1"></i> Crear hogar
            </a>
        </li>
        @if ($active)
            <li>
                <a class="dropdown-item" href="{{ route('households.show', $active) }}">
                    <i class="bi bi-gear me-1"></i> Configuración
                </a>
            </li>
        @endif
    </ul>
</div>
