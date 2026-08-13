@extends('layouts.app', ['title' => $household->name])

@section('content')
    <x-flash-messages />

    @php
        $isActive = active_household_id() === $household->id;
        $isOwner = $household->owner_id === Auth::id();
    @endphp

    {{-- Encabezado --}}
    <div class="d-flex flex-wrap justify-content-between align-items-start mb-4 gap-3">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <h1 class="h3 mb-0">{{ $household->name }}</h1>
                @if ($isActive)
                    <span class="badge text-bg-success rounded-pill">Activo</span>
                @endif
            </div>
            <p class="text-muted mb-0">
                <i class="bi bi-currency-exchange me-1"></i> {{ $household->currency }} &middot;
                <i class="bi bi-clock me-1"></i> {{ $household->timezone }} &middot;
                <i class="bi bi-people me-1"></i> {{ $household->members->count() }} miembros
            </p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            @if (! $isActive)
                <form method="POST" action="{{ route('households.activate', $household) }}">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-finlia">
                        <i class="bi bi-box-arrow-in-right me-1"></i> Activar
                    </button>
                </form>
            @endif
            @if ($isOwner)
                <a href="{{ route('households.edit', $household) }}" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-pencil me-1"></i> Editar
                </a>
                <button type="button" class="btn btn-sm btn-outline-danger"
                        data-bs-toggle="modal" data-bs-target="#deleteModal">
                    <i class="bi bi-trash me-1"></i> Eliminar
                </button>
            @endif
        </div>
    </div>

    <div class="row g-3">
        {{-- Columna principal: miembros + invitaciones --}}
        <div class="col-12 col-lg-8">

            {{-- Enlace de invitación recién generado --}}
            @if (session('invitation_link'))
                <div class="alert alert-success d-flex align-items-start gap-2 border-0 shadow-sm" role="alert">
                    <i class="bi bi-send-check-fill mt-1"></i>
                    <div class="flex-grow-1">
                        <strong>¡Invitación creada para {{ $invitationEmail }}!</strong>
                        <div class="small mt-1">Comparte este enlace (expira en 7 días). Cuando el correo no está configurado, debes enviarlo a mano:</div>
                        <div class="input-group input-group-sm mt-2">
                            <input type="text" class="form-control" value="{{ session('invitation_link') }}" readonly id="invitationLink" onclick="this.select()">
                            <button class="btn btn-outline-finlia" type="button" onclick="navigator.clipboard.writeText(document.getElementById('invitationLink').value)">
                                <i class="bi bi-clipboard"></i> Copiar
                            </button>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Enviar invitación (solo owner) --}}
            @if ($isOwner)
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-header border-0 bg-transparent fw-semibold">
                        <i class="bi bi-person-plus me-1"></i> Invitar miembro
                    </div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('households.invitations.store', $household) }}">
                            @csrf
                            <div class="row g-2 align-items-end">
                                <div class="col-12 col-sm-6">
                                    <label for="email" class="form-label small fw-semibold">Correo del invitado</label>
                                    <input type="email" name="email" id="email"
                                           class="form-control @error('email') is-invalid @enderror"
                                           value="{{ old('email') }}" placeholder="persona@correo.com" required>
                                    @error('email')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-7 col-sm-3">
                                    <label for="role" class="form-label small fw-semibold">Rol</label>
                                    <select name="role" id="role" class="form-select @error('role') is-invalid @enderror" required>
                                        <option value="member" @selected(old('role', 'member') === 'member')>Miembro</option>
                                        <option value="owner" @selected(old('role') === 'owner')>Administrador</option>
                                    </select>
                                    @error('role')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-5 col-sm-3">
                                    <button type="submit" class="btn btn-finlia w-100">
                                        <i class="bi bi-send me-1"></i> Invitar
                                    </button>
                                </div>
                            </div>
                            <div class="form-text">El invitado recibirá un enlace para unirse a este hogar.</div>
                        </form>
                    </div>
                </div>
            @endif

            {{-- Miembros --}}
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header border-0 bg-transparent fw-semibold">
                    <i class="bi bi-people me-1"></i> Miembros
                </div>
                <div class="list-group list-group-flush">
                    @foreach ($household->members as $member)
                        @php
                            $role = $member->pivot->role;
                            $memberIsOwner = $member->id === $household->owner_id;
                            $canRemove = $isOwner && ! $memberIsOwner;
                        @endphp
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <div class="d-flex align-items-center gap-2">
                                <span class="avatar-btn static-avatar">{{ strtoupper(substr($member->name, 0, 1)) }}</span>
                                <div>
                                    <div class="fw-semibold">
                                        {{ $member->name }}
                                        @if ($member->is(Auth::user()))
                                            <span class="badge text-bg-light">(tú)</span>
                                        @endif
                                    </div>
                                    <div class="small text-muted">{{ $member->email }}</div>
                                </div>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge @if($memberIsOwner) bg-finlia-subtle text-finlia @else text-bg-light text-muted @endif rounded-pill">
                                    {{ $role->label() }}
                                </span>
                                @if ($canRemove)
                                    <form method="POST"
                                          action="{{ route('households.members.destroy', [$household, $member]) }}"
                                          data-confirm="¿Eliminar a {{ $member->name }} del hogar?">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-icon text-danger" aria-label="Eliminar miembro">
                                            <i class="bi bi-x-lg"></i>
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Invitaciones enviadas (solo owner) --}}
            @if ($isOwner)
                <div class="card border-0 shadow-sm">
                    <div class="card-header border-0 bg-transparent fw-semibold">
                        <i class="bi bi-envelope-paper me-1"></i> Invitaciones enviadas
                    </div>
                    @if ($household->invitations->isEmpty())
                        <div class="card-body text-muted small">Sin invitaciones enviadas todavía.</div>
                    @else
                        <div class="list-group list-group-flush">
                            @foreach ($household->invitations as $invitation)
                                @php
                                    $badge = match($invitation->status->value) {
                                        'pending' => ['bg-warning-subtle', 'text-warning-emphasis'],
                                        'accepted' => ['bg-success-subtle', 'text-success-emphasis'],
                                        'expired' => ['bg-secondary-subtle', 'text-secondary-emphasis'],
                                        'revoked' => ['bg-danger-subtle', 'text-danger-emphasis'],
                                        default => ['bg-light', 'text-muted'],
                                    };
                                @endphp
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="fw-semibold">{{ $invitation->email }}</div>
                                        <div class="small text-muted">
                                            Rol: {{ $invitation->role->label() }} &middot;
                                            Expira: {{ $invitation->expires_at->format('d/m/Y') }}
                                        </div>
                                    </div>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="badge {{ $badge[0] }} {{ $badge[1] }} rounded-pill">
                                            {{ $invitation->status->label() }}
                                        </span>
                                        @if ($invitation->status->value === 'pending')
                                            <form method="POST" action="{{ route('households.invitations.destroy', [$household, $invitation]) }}"
                                                  data-confirm="¿Revocar la invitación a {{ $invitation->email }}?">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-icon text-danger" aria-label="Revocar">
                                                    <i class="bi bi-x-lg"></i>
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif
        </div>

        {{-- Columna lateral: resumen --}}
        <div class="col-12 col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header border-0 bg-transparent fw-semibold">
                    <i class="bi bi-info-circle me-1"></i> Resumen
                </div>
                <div class="card-body small">
                    <p class="mb-2"><strong class="d-block text-muted text-uppercase">Administrador</strong>
                        {{ $household->owner->name }}
                    </p>
                    <p class="mb-2"><strong class="d-block text-muted text-uppercase">Moneda</strong>
                        {{ $household->currency }}
                    </p>
                    <p class="mb-0"><strong class="d-block text-muted text-uppercase">Zona horaria</strong>
                        {{ $household->timezone }}
                    </p>
                </div>
            </div>
        </div>
    </div>

    {{-- Modal de eliminación --}}
    @if ($isOwner)
        <div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form method="POST" action="{{ route('households.destroy', $household) }}">
                        @csrf
                        @method('DELETE')
                        <div class="modal-header border-0">
                            <h5 class="modal-title"><i class="bi bi-exclamation-triangle text-danger me-1"></i> Eliminar hogar</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body">
                            ¿Seguro que deseas eliminar <strong>{{ $household->name }}</strong>?
                            Se eliminarán los datos asociados a este hogar. Esta acción no se puede deshacer.
                        </div>
                        <div class="modal-footer border-0">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-danger">
                                <i class="bi bi-trash me-1"></i> Eliminar definitivamente
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
@endsection
