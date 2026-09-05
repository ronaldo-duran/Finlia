@extends('layouts.app', ['title' => 'Mi perfil'])

@section('content')
    <x-flash-messages />

    @php
        $openPersonal = $errors->hasAny(['name', 'birth_date', 'region', 'gender']) && old('_section') === 'personal';
        $openPassword = $errors->hasAny(['current_password', 'password', 'password_confirmation']) && old('_section') === 'password';
        $openEmail    = $errors->has('email') && old('_section') === 'email';
        $openDeletion = $errors->has('current_password') && old('_section') === 'deletion';
    @endphp

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-1">
        <h1 class="h3 mb-0"><i class="bi bi-person-circle me-2"></i>Mi perfil</h1>
    </div>
    <p class="text-muted mb-4">
        Tu cuenta de Finlia: datos personales, contraseña y correo. Estos datos son tuyos y no se
        comparten con los hogares a los que perteneces — la configuración de cada hogar
        vive en <a href="{{ route('households.index') }}">Hogares</a>.
    </p>

    <div class="row g-3">
        <div class="col-12 col-lg-6 d-flex flex-column gap-3">

            {{-- ===================== Datos personales ===================== --}}
            <div class="card border-0">
                <div class="card-header border-0 bg-transparent d-flex justify-content-between align-items-center">
                    <span class="fw-semibold"><i class="bi bi-person me-1"></i> Datos personales</span>
                    <button class="btn btn-sm btn-outline-secondary"
                        data-bs-toggle="collapse"
                        data-bs-target="#editPersonal"
                        aria-expanded="{{ $openPersonal ? 'true' : 'false' }}"
                        aria-controls="editPersonal">
                        <i class="bi bi-pencil me-1"></i> Editar
                    </button>
                </div>
                <div class="card-body">
                    {{-- Vista de solo lectura --}}
                    <div id="viewPersonal" class="{{ $openPersonal ? 'd-none' : '' }}">
                        <dl class="row mb-0 small">
                            <dt class="col-5 fw-normal text-muted">Nombre</dt>
                            <dd class="col-7 mb-2">{{ $user->name }}</dd>
                            <dt class="col-5 fw-normal text-muted">Nacimiento</dt>
                            <dd class="col-7 mb-2">{{ $user->birth_date?->format('d/m/Y') ?? '—' }}</dd>
                            <dt class="col-5 fw-normal text-muted">Región</dt>
                            <dd class="col-7 mb-2">{{ $user->region ? \App\Enums\ColombianRegion::from($user->region)->label() : '—' }}</dd>
                            <dt class="col-5 fw-normal text-muted">Género</dt>
                            <dd class="col-7 mb-0">{{ $user->gender ? \App\Enums\Gender::from($user->gender)->label() : '—' }}</dd>
                        </dl>
                    </div>

                    {{-- Formulario de edición (colapsado por defecto) --}}
                    <div class="collapse {{ $openPersonal ? 'show' : '' }}" id="editPersonal">
                        <form method="POST" action="{{ route('profile.update') }}">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="_section" value="personal">

                            <x-form-input label="Nombre" name="name"
                                :value="old('name', $user->name)" required autocomplete="name" />

                            <x-form-input label="Fecha de nacimiento" name="birth_date" type="date"
                                :value="old('birth_date', $user->birth_date?->toDateString())"
                                :min="\Carbon\Carbon::create(1900, 1, 1)->toDateString()"
                                :max="now()->subYears(18)->toDateString()" required />

                            <x-form-select label="Región" name="region"
                                :options="\App\Enums\ColombianRegion::options()"
                                :selected="old('region', $user->region)" placeholder="Sin definir"
                                help="Departamento donde vives." />

                            <x-form-select label="Género" name="gender"
                                :options="\App\Enums\Gender::options()"
                                :selected="old('gender', $user->gender)" placeholder="Prefiero no decirlo" />

                            <div class="d-flex gap-2 mt-3">
                                <button type="submit" class="btn btn-finlia btn-sm">
                                    <i class="bi bi-check-lg me-1"></i> Guardar
                                </button>
                                <button type="button" class="btn btn-sm btn-link text-secondary px-0"
                                    data-bs-toggle="collapse" data-bs-target="#editPersonal">
                                    Cancelar
                                </button>
                            </div>
                        </form>
                        <div class="form-text mt-3">
                            <i class="bi bi-info-circle me-1"></i>
                            Tu fecha de nacimiento confirma que eres mayor de edad; región y género
                            (opcionales) solo alimentan estadísticas del producto. Nunca se usan
                            para decisiones financieras ni se comparten.
                        </div>
                    </div>
                </div>
            </div>

            {{-- ===================== Contraseña ===================== --}}
            <div class="card border-0">
                <div class="card-header border-0 bg-transparent d-flex justify-content-between align-items-center">
                    <span class="fw-semibold"><i class="bi bi-shield-lock me-1"></i> Contraseña</span>
                    <button class="btn btn-sm btn-outline-secondary"
                        data-bs-toggle="collapse"
                        data-bs-target="#editPassword"
                        aria-expanded="{{ $openPassword ? 'true' : 'false' }}"
                        aria-controls="editPassword">
                        <i class="bi bi-pencil me-1"></i> Cambiar
                    </button>
                </div>
                <div class="card-body">
                    {{-- Vista de solo lectura --}}
                    <div id="viewPassword" class="{{ $openPassword ? 'd-none' : '' }}">
                        <p class="text-muted small mb-0">
                            Al cambiarla cerramos las demás sesiones de tu cuenta; esta queda activa
                            y te enviamos un aviso a tu correo.
                        </p>
                    </div>

                    {{-- Formulario (colapsado) --}}
                    <div class="collapse {{ $openPassword ? 'show' : '' }}" id="editPassword">
                        <form method="POST" action="{{ route('profile.password.update') }}">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="_section" value="password">

                            <x-form-input label="Contraseña actual" name="current_password" type="password"
                                required autocomplete="current-password" />

                            <x-form-input label="Contraseña nueva" name="password" type="password"
                                required autocomplete="new-password"
                                help="Mínimo 8 caracteres." />

                            <x-form-input label="Repite la contraseña nueva" name="password_confirmation"
                                type="password" required autocomplete="new-password" />

                            <div class="d-flex gap-2 mt-3">
                                <button type="submit" class="btn btn-finlia btn-sm">
                                    <i class="bi bi-key me-1"></i> Cambiar contraseña
                                </button>
                                <button type="button" class="btn btn-sm btn-link text-secondary px-0"
                                    data-bs-toggle="collapse" data-bs-target="#editPassword">
                                    Cancelar
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-6 d-flex flex-column gap-3">

            {{-- ===================== Correo ===================== --}}
            <div class="card border-0">
                <div class="card-header border-0 bg-transparent d-flex justify-content-between align-items-center">
                    <span class="fw-semibold"><i class="bi bi-envelope me-1"></i> Correo</span>
                    <button class="btn btn-sm btn-outline-secondary"
                        data-bs-toggle="collapse"
                        data-bs-target="#editEmail"
                        aria-expanded="{{ $openEmail ? 'true' : 'false' }}"
                        aria-controls="editEmail">
                        <i class="bi bi-pencil me-1"></i> Cambiar
                    </button>
                </div>
                <div class="card-body">
                    {{-- Vista de solo lectura --}}
                    <div id="viewEmail" class="{{ $openEmail ? 'd-none' : '' }}">
                        <div class="d-flex align-items-center gap-2">
                            <span class="fw-semibold">{{ $user->email }}</span>
                            @if ($user->email_verified_at)
                                <span class="badge rounded-pill text-bg-success">Confirmado</span>
                            @endif
                        </div>
                    </div>

                    @if ($user->pending_email)
                        <div class="alert alert-info d-flex align-items-start gap-2 mt-2" role="alert">
                            <i class="bi bi-hourglass-split mt-1"></i>
                            <div>
                                Cambio pendiente a <strong>{{ $user->pending_email }}</strong>.
                                Enviamos un enlace de confirmación a esa bandeja; vence el
                                <strong>{{ $pendingExpiresAt?->timezone(config('app.timezone'))->format('d/m/Y, g:i a') }}</strong>.
                                Si la pides de nuevo, generamos un enlace nuevo y anulamos este.
                            </div>
                        </div>
                    @endif

                    {{-- Formulario de cambio de correo (colapsado) --}}
                    <div class="collapse {{ $openEmail ? 'show' : '' }} mt-3" id="editEmail">
                        <form method="POST" action="{{ route('profile.email.update') }}">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="_section" value="email">

                            <x-form-input label="Correo nuevo" name="email" type="email" required
                                autocomplete="email" placeholder="nuevo@ejemplo.com"
                                help="Te enviaremos un enlace a esa bandeja: el cambio solo se completa al confirmarlo." />

                            <div class="d-flex gap-2 mt-3">
                                <button type="submit" class="btn btn-finlia btn-sm">
                                    <i class="bi bi-send me-1"></i> Enviar confirmación
                                </button>
                                <button type="button" class="btn btn-sm btn-link text-secondary px-0"
                                    data-bs-toggle="collapse" data-bs-target="#editEmail">
                                    Cancelar
                                </button>
                            </div>
                        </form>
                        <div class="form-text mt-3">
                            <i class="bi bi-shield-check me-1"></i>
                            Tu correo confirmado es la llave de la cuenta (recuperación, avisos). Por eso
                            el nuevo pasa por su propia confirmación — y avisamos al anterior del cambio.
                        </div>
                    </div>
                </div>
            </div>

            {{-- ===================== Portabilidad — exportar datos ===================== --}}
            <div class="card border-0">
                <div class="card-header border-0 bg-transparent fw-semibold">
                    <i class="bi bi-download me-1"></i> Mis datos
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-3">
                        Descarga un ZIP con todos los datos de tu hogar activo: cuentas, movimientos,
                        deudas, metas, presupuestos y más — en CSV (Excel) y JSON.
                        Solo incluye tus datos personales, nunca los de otros miembros.
                        <a href="{{ route('data.policy') }}" class="text-decoration-none">Más información</a>.
                    </p>
                    <a href="{{ route('profile.export') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-file-zip me-1"></i> Exportar mis datos
                    </a>
                    <div class="form-text mt-2">Máximo 3 descargas por día.</div>
                </div>
            </div>

            {{-- ===================== Zona de peligro — eliminar cuenta ===================== --}}
            <div class="card border-0 border-danger-subtle">
                <div class="card-header border-0 bg-transparent fw-semibold text-danger-emphasis">
                    <i class="bi bi-exclamation-triangle me-1"></i> Zona de peligro
                </div>
                <div class="card-body">
                    <p class="text-muted mb-3" style="font-size:.93rem;">
                        Al solicitar la eliminación tu cuenta queda <strong>suspendida 30 días</strong>.
                        Durante ese tiempo puedes cambiar de opinión e iniciar sesión para reactivarla.
                        Transcurrido el plazo, todos tus datos se eliminan de forma permanente.
                    </p>

                    <button class="btn btn-outline-danger btn-sm"
                        data-bs-toggle="collapse"
                        data-bs-target="#formEliminarCuenta"
                        aria-expanded="{{ $openDeletion ? 'true' : 'false' }}"
                        aria-controls="formEliminarCuenta">
                        <i class="bi bi-trash me-1"></i> Solicitar eliminación de cuenta
                    </button>

                    <div class="collapse {{ $openDeletion ? 'show' : '' }} mt-3" id="formEliminarCuenta">
                        <form method="POST" action="{{ route('profile.deletion.store') }}"
                              onsubmit="return confirm('¿Seguro/a? Tu cuenta se suspenderá y se eliminará en 30 días si no la reactivás.')">
                            @csrf
                            @method('DELETE')
                            <input type="hidden" name="_section" value="deletion">

                            <x-form-input label="Confirma tu contraseña" name="current_password"
                                type="password" required autocomplete="current-password"
                                help="Necesitamos verificar que eres tú." />

                            <button type="submit" class="btn btn-danger btn-sm">
                                <i class="bi bi-trash me-1"></i> Sí, quiero eliminar mi cuenta
                            </button>
                        </form>
                    </div>
                </div>
            </div>

        </div>
    </div>

    @push('scripts')
    <script>
        (function () {
            [
                ['editPersonal', 'viewPersonal'],
                ['editPassword', 'viewPassword'],
                ['editEmail',    'viewEmail'],
            ].forEach(([collapseId, viewId]) => {
                const collapse = document.getElementById(collapseId);
                const view = document.getElementById(viewId);
                if (!collapse || !view) return;
                collapse.addEventListener('show.bs.collapse', () => view.classList.add('d-none'));
                collapse.addEventListener('hide.bs.collapse', () => view.classList.remove('d-none'));
            });
        })();
    </script>
    @endpush
@endsection
