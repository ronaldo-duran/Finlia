{{-- Solo nombre del hogar, rol y caducidad: la vista es pública y no debe
     mostrar nada del dinero del hogar. --}}
<i class="bi bi-envelope-open-heart fs-1 text-finlia d-block mb-3"></i>
<h1 class="h4 mb-1">¡Tienes una invitación!</h1>
<p class="text-muted mb-4">
    Te han invitado a unirte al hogar
    <strong class="text-finlia">{{ $invitation->household->name }}</strong>
    como <strong>{{ $invitation->role->label() }}</strong>.
</p>

<ul class="list-unstyled small text-muted mb-4">
    <li><i class="bi bi-envelope me-1"></i> Invitación para: {{ $invitation->email }}</li>
    <li><i class="bi bi-clock me-1"></i> Expira el {{ $invitation->expires_at->format('d/m/Y H:i') }}</li>
</ul>
