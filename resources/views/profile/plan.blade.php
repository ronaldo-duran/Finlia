@extends('layouts.app', ['title' => 'Tu plan'])

@section('content')
    <x-flash-messages />

    @php
        $isPremium = $plan->slug === \App\Enums\PlanSlug::Premium->value;
        $premiumForAll = $premiumForAll ?? false;
    @endphp

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-1">
        <h1 class="h3 mb-0"><i class="bi bi-stars me-2"></i>Tu plan</h1>
        <a href="{{ route('profile.edit') }}" class="btn btn-sm btn-link text-secondary">
            <i class="bi bi-arrow-left me-1"></i> Volver al perfil
        </a>
    </div>
    <p class="text-muted mb-4">
        El plan lo tiene cada hogar. Aquí ves el del hogar activo
        (<strong>{{ $household->name }}</strong>) y cuánto llevas usado.
    </p>

    <div class="row g-3">
        <div class="col-12 col-lg-6 d-flex flex-column gap-3">

            <div class="card border-0">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between gap-3">
                        <div>
                            <p class="text-muted mb-1 small">Plan del hogar</p>
                            <h2 class="h4 mb-1">
                                {{ $plan->name }}
                                @if ($isPremium)
                                    <span class="badge bg-primary-subtle text-primary ms-1">Premium</span>
                                @else
                                    <span class="badge bg-secondary-subtle text-secondary ms-1">Gratis</span>
                                @endif
                            </h2>
                            @if ($premiumForAll)
                                <p class="text-muted small mb-0">
                                    Mientras Finlia termina de definir qué lleva Premium, todo el mundo lo tiene abierto.
                                </p>
                            @elseif ($subscription?->ends_at)
                                <p class="text-muted small mb-0">
                                    Vence el {{ $subscription->ends_at->format('d/m/Y') }}.
                                </p>
                            @else
                                <p class="text-muted small mb-0">
                                    Sin fecha de vencimiento.
                                </p>
                            @endif
                        </div>
                        @if (! $isPremium && $plan->price_monthly !== null)
                            <div class="text-end">
                                <div class="text-muted small">Premium desde</div>
                                <div class="fw-semibold">@money($plan->price_monthly) / mes</div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="card border-0">
                <div class="card-header border-0 bg-transparent">
                    <span class="fw-semibold"><i class="bi bi-graph-up me-1"></i> Uso este mes</span>
                </div>
                <div class="card-body">
                    <dl class="row mb-0 small">
                        <dt class="col-7 fw-normal text-muted">Hogares que administras</dt>
                        <dd class="col-5 mb-2 text-end">
                            {{ $usage['households']['used'] }}
                            @if ($usage['households']['limit'] !== null)
                                / {{ $usage['households']['limit'] }}
                            @else
                                <span class="text-muted">sin límite</span>
                            @endif
                        </dd>
                        <dt class="col-7 fw-normal text-muted">Miembros del hogar</dt>
                        <dd class="col-5 mb-2 text-end">
                            {{ $usage['members']['used'] }}
                            @if ($usage['members']['limit'] !== null)
                                / {{ $usage['members']['limit'] }}
                            @else
                                <span class="text-muted">sin límite</span>
                            @endif
                        </dd>
                        <dt class="col-7 fw-normal text-muted">Encuestas de compras</dt>
                        <dd class="col-5 mb-0 text-end">
                            {{ $usage['surveys']['used'] }}
                            @if ($usage['surveys']['limit'] !== null)
                                / {{ $usage['surveys']['limit'] }} al mes
                            @else
                                <span class="text-muted">sin límite</span>
                            @endif
                        </dd>
                    </dl>
                </div>
            </div>

            @if (! $isPremium)
                <div class="card border-0 bg-primary-subtle">
                    <div class="card-body">
                        <h3 class="h6 mb-2"><i class="bi bi-stars me-1"></i> Premium</h3>
                        <p class="small text-muted mb-2">
                            Un solo hogar y dos personas son suficientes para casi todo. Cuando quieras
                            <strong>hogares extra</strong>, <strong>encuestas sin tope</strong> o las
                            funciones que están por venir (chat con IA, PDF de reportes, autoconocimiento),
                            eso es Premium.
                        </p>
                        <p class="small text-muted mb-3">
                            Desde <strong>@money($plan->slug === \App\Enums\PlanSlug::Free->value ? 9900 : $plan->price_monthly)</strong>
                            al mes o <strong>@money(79000)</strong> al año.
                        </p>
                        <button class="btn btn-finlia btn-sm" disabled>
                            <i class="bi bi-lock me-1"></i> Próximamente
                        </button>
                        <p class="small text-muted mt-2 mb-0">
                            La pasarela de pago llega con la próxima entrega. Mientras tanto podemos activarlo
                            manualmente si nos escribes a
                            <a href="{{ route('contact.create') }}">contacto</a>.
                        </p>
                    </div>
                </div>
            @endif

        </div>
    </div>
@endsection
