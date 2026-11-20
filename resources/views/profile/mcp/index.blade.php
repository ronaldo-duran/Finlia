@extends('layouts.app', ['title' => 'Tokens MCP'])

@section('content')
    <x-flash-messages />

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-1">
        <h1 class="h3 mb-0"><i class="bi bi-key me-2"></i>Tokens MCP</h1>
        <a href="{{ route('profile.edit') }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Volver al perfil
        </a>
    </div>
    <p class="text-muted mb-4">
        Los tokens MCP permiten que un asistente de IA que uses (Claude, por ejemplo) consulte los
        datos del hogar activo mediante el servidor MCP de Finlia. Cada token está atado al hogar
        que estaba activo cuando lo emitiste y nunca puede ver datos de otro.
    </p>

    @if (session('mcp_new_token'))
        <div class="alert alert-success d-flex flex-column gap-2" role="alert">
            <div><strong><i class="bi bi-shield-check me-1"></i> Token creado</strong></div>
            <div class="small">Guárdalo ahora en un lugar seguro: no volverá a mostrarse.</div>
            <code class="d-block p-2 bg-body border rounded user-select-all" style="word-break: break-all;">{{ session('mcp_new_token') }}</code>
        </div>
    @endif

    <div class="card border-0 mb-4">
        <div class="card-header border-0 bg-transparent">
            <span class="fw-semibold"><i class="bi bi-plus-circle me-1"></i> Emitir un token nuevo</span>
        </div>
        <div class="card-body">
            @if ($activeHousehold === null)
                <p class="text-muted mb-0">Antes de emitir un token, elige un hogar activo.</p>
            @else
                <p class="small text-muted">
                    El token quedará atado a tu hogar activo: <strong>{{ $activeHousehold->name }}</strong>.
                    Si cambias de hogar, emite un token nuevo desde ahí.
                </p>
                <form method="POST" action="{{ route('profile.mcp.store') }}" class="row g-2">
                    @csrf
                    <div class="col-12 col-md-6">
                        <x-form-input label="Nombre" name="name" :value="old('name')"
                            required placeholder="Claude Desktop, portátil, etc." />
                    </div>
                    <div class="col-12 col-md-4">
                        <x-form-input label="Caduca en (días)" name="expires_in_days" type="number"
                            min="1" max="3650" :value="old('expires_in_days')"
                            help="Opcional. Vacío = sin vencimiento." />
                    </div>
                    <div class="col-12 col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-key me-1"></i> Emitir
                        </button>
                    </div>
                </form>
            @endif
        </div>
    </div>

    <div class="card border-0">
        <div class="card-header border-0 bg-transparent">
            <span class="fw-semibold"><i class="bi bi-list-ul me-1"></i> Tus tokens</span>
        </div>
        <div class="card-body">
            @if ($tokens->isEmpty())
                <p class="text-muted mb-0">Aún no has emitido ningún token.</p>
            @else
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Hogar</th>
                                <th>Estado</th>
                                <th>Último uso</th>
                                <th class="text-end"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($tokens as $token)
                                <tr>
                                    <td>{{ $token->name }}</td>
                                    <td class="small text-muted">{{ $token->household?->name ?? '—' }}</td>
                                    <td class="small">
                                        @if ($token->isRevoked())
                                            <span class="badge text-bg-secondary">Revocado</span>
                                        @elseif ($token->isExpired())
                                            <span class="badge text-bg-warning">Vencido</span>
                                        @else
                                            <span class="badge text-bg-success">Activo</span>
                                        @endif
                                        @if ($token->expires_at && ! $token->isRevoked())
                                            <span class="text-muted d-block">Caduca {{ $token->expires_at->format('d/m/Y') }}</span>
                                        @endif
                                    </td>
                                    <td class="small text-muted">
                                        {{ $token->last_used_at?->diffForHumans() ?? 'Nunca' }}
                                    </td>
                                    <td class="text-end">
                                        @if (! $token->isRevoked())
                                            <form method="POST" action="{{ route('profile.mcp.destroy', $token) }}"
                                                onsubmit="return confirm('¿Revocar este token? La acción no se puede deshacer.');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                                    <i class="bi bi-x-circle me-1"></i> Revocar
                                                </button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <div class="mt-4 small text-muted">
        <p class="mb-1"><strong>Endpoint:</strong> <code>POST {{ url('/mcp') }}</code></p>
        <p class="mb-0">
            Envía el token como <code>Authorization: Bearer finlia_mcp_...</code>. Consulta
            <a href="{{ route('changelog.show') }}">las notas de versión</a> para ver qué tools están disponibles.
        </p>
    </div>
@endsection
