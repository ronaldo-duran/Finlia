@extends('layouts.guest', ['title' => 'Sin aceptar los términos', 'subtitle' => 'Hasta aquí'])

@section('content')
    <div class="text-center mb-4">
        <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-finlia-subtle mb-3" style="width:64px; height:64px;">
            <i class="bi bi-shield-x fs-3 text-finlia"></i>
        </div>
        <h1 class="h4 fw-bold mb-2">Prefieres no aceptar</h1>
        <p class="text-muted small mb-0">
            Sin aceptar los términos no puedes usar Finlia. Y no pasa nada
            más: tu cuenta, tus hogares y tus datos quedan exactamente como
            están — rechazar no borra nada.
        </p>
    </div>

    <div class="d-grid mb-2">
        <a href="{{ route('terms.accept') }}" class="btn btn-finlia py-2">
            <i class="bi bi-arrow-counterclockwise me-1"></i> Volver a los términos
        </a>
    </div>
    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <div class="d-grid">
            <button type="submit" class="btn btn-outline-secondary py-2">
                <i class="bi bi-box-arrow-right me-1"></i> Cerrar sesión
            </button>
        </div>
    </form>
@endsection

@section('actions')
    {{-- Los enlaces de exportar datos (plan 06) y eliminar cuenta (plan 05)
         se añaden aquí cuando existan: esta pantalla es su puerta natural. --}}
    <p class="text-center text-muted small mt-3 mb-0">
        ¿Quieres llevarte tus datos o eliminar tu cuenta?
        Esas opciones llegarán pronto a esta pantalla.
    </p>
@endsection
