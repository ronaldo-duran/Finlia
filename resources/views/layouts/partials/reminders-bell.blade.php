@php
    /**
     * Campanita de recordatorios (Épica 9, in-app — ADR-0015).
     * $bellSummary la inyecta el view composer de AppServiceProvider:
     * null si no hay sesión/hogar, y attention=0 si el hogar los desactivó.
     */
    $bell = $bellSummary ?? null;
@endphp
@if ($bell !== null)
    <li class="nav-item dropdown">
        <button type="button" class="btn-icon position-relative" data-bs-toggle="dropdown"
                aria-expanded="false" aria-label="Recordatorios">
            <i class="bi bi-bell"></i>
            @if ($bell['attention'] > 0)
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill text-bg-danger"
                      style="font-size:.6rem">{{ $bell['attention'] }}</span>
            @endif
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
            <li><h6 class="dropdown-header"><i class="bi bi-bell me-1"></i> Recordatorios</h6></li>

            @if (! $bell['enabled'])
                <li><span class="dropdown-item-text small text-muted">Desactivados en la configuración del hogar.</span></li>
            @elseif ($bell['attention'] === 0)
                <li><span class="dropdown-item-text small text-muted">Nada urgente. Todo al día ✨</span></li>
            @else
                <li><span class="dropdown-item-text small">
                    @if ($bell['overdue'] > 0)
                        <strong class="text-danger">{{ $bell['overdue'] }} {{ $bell['overdue'] === 1 ? 'obligación vencida' : 'obligaciones vencidas' }}</strong>@if ($bell['upcoming'] > 0) · @endif
                    @endif
                    @if ($bell['upcoming'] > 0)
                        <strong class="text-warning-emphasis">{{ $bell['upcoming'] }} {{ $bell['upcoming'] === 1 ? 'próxima' : 'próximas' }}</strong>
                    @endif
                </span></li>
            @endif

            <li><hr class="dropdown-divider"></li>
            <li>
                <a class="dropdown-item" href="{{ route('reminders.index') }}">
                    <i class="bi bi-list-check me-1"></i> Ver recordatorios
                </a>
            </li>
        </ul>
    </li>
@endif
