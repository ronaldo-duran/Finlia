@extends('layouts.app', ['title' => 'Autorizar acceso'])

@section('content')
    <div class="row justify-content-center">
        <div class="col-12 col-md-8 col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <div class="text-center mb-3">
                        <div class="display-6"><i class="bi bi-shield-lock"></i></div>
                        <h1 class="h4 mt-2">Autorizar acceso a Finlia</h1>
                    </div>

                    <p class="text-center mb-4">
                        <strong>{{ $client->client_name }}</strong> quiere acceder a tus datos
                        financieros en Finlia.
                    </p>

                    <div class="alert alert-info small">
                        <strong><i class="bi bi-info-circle me-1"></i> Qué significa esto</strong>
                        <ul class="mb-0 mt-2">
                            <li>Solo podrá leer datos del hogar que elijas aquí abajo.</li>
                            <li>Nunca podrá ver datos de otros hogares.</li>
                            <li>Podrás revocar el acceso desde <a href="{{ route('profile.mcp.index') }}">Perfil → Tokens MCP</a>.</li>
                        </ul>
                    </div>

                    @if ($households->isEmpty())
                        <p class="text-muted">
                            No tienes ningún hogar al que autorizar acceso. Crea uno antes de conectar
                            un asistente externo.
                        </p>
                    @else
                        <form method="POST" action="{{ route('oauth.authorize.approve') }}">
                            @csrf
                            @foreach ($params as $key => $value)
                                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                            @endforeach

                            <div class="mb-3">
                                <label for="household_id" class="form-label fw-semibold">
                                    Hogar al que dar acceso
                                </label>
                                <select id="household_id" name="household_id" class="form-select" required>
                                    @foreach ($households as $household)
                                        <option value="{{ $household->id }}"
                                            @selected($activeHousehold && $activeHousehold->id === $household->id)>
                                            {{ $household->name }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="form-text">
                                    El cliente nunca podrá cambiar el hogar: queda fijado aquí.
                                </div>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" name="decision" value="approve" class="btn btn-primary">
                                    <i class="bi bi-check2-circle me-1"></i> Autorizar
                                </button>
                                <button type="submit" name="decision" value="deny" class="btn btn-outline-secondary">
                                    Cancelar
                                </button>
                            </div>
                        </form>
                    @endif

                    <hr class="my-4">
                    <p class="text-muted small mb-0">
                        Vas a autorizar al cliente registrado con <code>client_id</code>
                        <code>{{ $client->client_id }}</code>. Si no reconoces esta solicitud,
                        cancela y revisa tus conectores.
                    </p>
                </div>
            </div>
        </div>
    </div>
@endsection
