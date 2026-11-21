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

    <div class="card border-0 mt-4">
        <div class="card-header border-0 bg-transparent">
            <span class="fw-semibold"><i class="bi bi-plug me-1"></i> Cómo conectarlo desde tu asistente</span>
        </div>
        <div class="card-body">
            <p class="small text-muted mb-3">
                Endpoint: <code>POST {{ url('/mcp') }}</code> con header
                <code>Authorization: Bearer finlia_mcp_...</code>. En los ejemplos sustituye
                <code>TU_TOKEN</code> por el token que acabas de emitir arriba.
            </p>

            <div class="accordion" id="mcpConnectAccordion">
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                            data-bs-target="#mcpConnectClaudeDesktop" aria-expanded="false">
                            <i class="bi bi-chat-square-text me-2"></i> Claude Desktop (Mac / Windows)
                        </button>
                    </h2>
                    <div id="mcpConnectClaudeDesktop" class="accordion-collapse collapse"
                        data-bs-parent="#mcpConnectAccordion">
                        <div class="accordion-body small">
                            <ol class="mb-2">
                                <li>Abre Claude Desktop, ve a <strong>Ajustes → Developer → Edit Config</strong>.</li>
                                <li>Pega este bloque dentro de <code>mcpServers</code> y guarda:</li>
                            </ol>
<pre class="p-3 bg-body border rounded"><code>{
  "mcpServers": {
    "finlia": {
      "url": "{{ url('/mcp') }}",
      "headers": {
        "Authorization": "Bearer TU_TOKEN"
      }
    }
  }
}</code></pre>
                            <p class="mb-0">
                                Reinicia Claude Desktop. Si tu versión no acepta <code>url</code> directo,
                                usa el puente <code>mcp-remote</code>:
                            </p>
<pre class="p-3 bg-body border rounded mb-0"><code>{
  "mcpServers": {
    "finlia": {
      "command": "npx",
      "args": ["-y", "mcp-remote", "{{ url('/mcp') }}",
               "--header", "Authorization: Bearer TU_TOKEN"]
    }
  }
}</code></pre>
                        </div>
                    </div>
                </div>

                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                            data-bs-target="#mcpConnectClaudeCode" aria-expanded="false">
                            <i class="bi bi-terminal me-2"></i> Claude Code (CLI)
                        </button>
                    </h2>
                    <div id="mcpConnectClaudeCode" class="accordion-collapse collapse"
                        data-bs-parent="#mcpConnectAccordion">
                        <div class="accordion-body small">
                            <p>Desde una terminal:</p>
<pre class="p-3 bg-body border rounded mb-2"><code>claude mcp add --transport http finlia {{ url('/mcp') }} \
  --header "Authorization: Bearer TU_TOKEN"</code></pre>
                            <p class="mb-0">
                                Dentro de Claude Code escribe <code>/mcp</code> para verificar que el
                                servidor <strong>finlia</strong> aparece como activo.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                            data-bs-target="#mcpConnectCursor" aria-expanded="false">
                            <i class="bi bi-code-slash me-2"></i> Cursor
                        </button>
                    </h2>
                    <div id="mcpConnectCursor" class="accordion-collapse collapse"
                        data-bs-parent="#mcpConnectAccordion">
                        <div class="accordion-body small">
                            <p>Edita <code>~/.cursor/mcp.json</code> (o el del proyecto en
                                <code>.cursor/mcp.json</code>) y añade:</p>
<pre class="p-3 bg-body border rounded mb-0"><code>{
  "mcpServers": {
    "finlia": {
      "url": "{{ url('/mcp') }}",
      "headers": {
        "Authorization": "Bearer TU_TOKEN"
      }
    }
  }
}</code></pre>
                        </div>
                    </div>
                </div>

                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                            data-bs-target="#mcpConnectChatgpt" aria-expanded="false">
                            <i class="bi bi-robot me-2"></i> ChatGPT
                        </button>
                    </h2>
                    <div id="mcpConnectChatgpt" class="accordion-collapse collapse"
                        data-bs-parent="#mcpConnectAccordion">
                        <div class="accordion-body small">
                            <p class="mb-2">
                                ChatGPT soporta conectores MCP, pero exige OAuth 2.1 con PKCE, no tokens
                                personales. Hoy Finlia no publica OAuth, así que <strong>la integración
                                nativa con ChatGPT no está disponible todavía</strong>. Está planeada en
                                un PR posterior.
                            </p>
                            <p class="mb-0">
                                Mientras tanto puedes usar Claude Desktop o Claude Code con el mismo
                                token, o cualquier cliente MCP que acepte <code>Authorization: Bearer</code>.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                            data-bs-target="#mcpConnectOther" aria-expanded="false">
                            <i class="bi bi-three-dots me-2"></i> Otro cliente MCP
                        </button>
                    </h2>
                    <div id="mcpConnectOther" class="accordion-collapse collapse"
                        data-bs-parent="#mcpConnectAccordion">
                        <div class="accordion-body small">
                            <p class="mb-2">
                                Zed, Continue, cline, Goose y la mayoría de clientes MCP aceptan un
                                servidor HTTP con header de autorización. Lo que necesitas pasarles:
                            </p>
                            <ul class="mb-2">
                                <li>URL: <code>{{ url('/mcp') }}</code></li>
                                <li>Transporte: <code>http</code> (streamable HTTP, una petición por llamada).</li>
                                <li>Header: <code>Authorization: Bearer TU_TOKEN</code></li>
                                <li>Formato: JSON-RPC 2.0 (<code>initialize</code>, <code>tools/list</code>, <code>tools/call</code>).</li>
                            </ul>
                            <p class="mb-0">
                                Prueba rápida sin cliente:
                            </p>
<pre class="p-3 bg-body border rounded mb-0"><code>curl -X POST {{ url('/mcp') }} \
  -H "Authorization: Bearer TU_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","id":1,"method":"tools/list"}'</code></pre>
                        </div>
                    </div>
                </div>
            </div>

            <p class="small text-muted mt-3 mb-0">
                Consulta <a href="{{ route('changelog.show') }}">las notas de versión</a> para ver qué
                tools están disponibles en cada entrega.
            </p>
        </div>
    </div>
@endsection
