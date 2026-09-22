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
        <ul class="dropdown-menu dropdown-menu-end" style="min-width: 18rem;">
            <li><h6 class="dropdown-header"><i class="bi bi-bell me-1"></i> Recordatorios</h6></li>

            @if (! $bell['enabled'])
                <li><span class="dropdown-item-text small text-muted">Desactivados en la configuración del hogar.</span></li>
            @elseif ($bell['attention'] === 0)
                <li><span class="dropdown-item-text small text-muted">Nada urgente. Todo al día</span></li>
            @else
                @php $preview = $bell['preview'] ?? []; @endphp
                @foreach ($preview as $item)
                    @php
                        $days = $item['days_remaining'];
                        [$dot, $label] = $item['status'] === 'overdue'
                            ? ['text-danger', 'Vencida hace '.abs($days).' '.(abs($days) === 1 ? 'día' : 'días')]
                            : ['text-warning-emphasis', $days === 0 ? 'Vence hoy' : 'En '.$days.' '.($days === 1 ? 'día' : 'días')];
                    @endphp
                    <li><span class="dropdown-item-text small d-flex gap-2 align-items-baseline">
                        <i class="bi bi-dot fs-4 lh-1 {{ $dot }}"></i>
                        <span class="min-w-0 flex-grow-1">
                            <span class="d-block text-truncate fw-semibold">{{ $item['title'] }}</span>
                            <span class="text-muted">{{ $label }}</span>
                        </span>
                    </span></li>
                @endforeach
                @if ($bell['attention'] > count($preview))
                    <li><span class="dropdown-item-text small text-muted">
                        y {{ $bell['attention'] - count($preview) }} más…
                    </span></li>
                @endif
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
