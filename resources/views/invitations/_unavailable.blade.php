<div class="alert alert-warning border-0 mb-3" role="alert">
    @if ($invitation->isPending() && $invitation->isExpired())
        <i class="bi bi-clock-history me-1"></i>
        Esta invitación ha expirado.
    @else
        <i class="bi bi-exclamation-triangle me-1"></i>
        Esta invitación ya no está disponible (estado: {{ $invitation->status->label() }}).
    @endif
</div>
