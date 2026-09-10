@extends('layouts.app', ['title' => 'Reportar un error'])

@section('content')
    <x-flash-messages />

    <div class="d-flex align-items-center gap-2 mb-4">
        <a href="{{ url()->previous() }}" class="btn-icon" aria-label="Volver">
            <i class="bi bi-arrow-left"></i>
        </a>
        <h1 class="h4 mb-0">Reportar un error</h1>
    </div>

    <div class="card p-4">
        <p class="text-secondary">
            Cuéntanos qué esperabas que pasara y qué pasó en su lugar. Cuanto más concreto,
            antes lo arreglamos.
        </p>

        <form method="POST" action="{{ route('bug-report.store') }}" novalidate>
            @csrf

            <div class="mb-3">
                <label for="body" class="form-label fw-semibold">
                    ¿Qué pasó? <span class="text-danger" aria-hidden="true">*</span>
                </label>
                <textarea id="body" name="body" rows="7" required autofocus
                          class="form-control @error('body') is-invalid @enderror"
                          placeholder="Ej: registré un gasto de mercado y el saldo de la cuenta no cambió.">{{ old('body') }}</textarea>
                @error('body')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            {{-- Contexto técnico: lo rellena el navegador al cargar la página, no
                 el usuario. Le ahorra tener que saber su versión o su navegador,
                 que es justo lo que nunca sabe. --}}
            <input type="hidden" name="origen" id="reporte-origen" value="{{ url()->previous() }}">
            <input type="hidden" name="viewport" id="reporte-viewport" value="">

            <div class="campo-trampa" aria-hidden="true">
                <label for="sitio_web">No rellenes este campo</label>
                <input type="text" id="sitio_web" name="sitio_web" tabindex="-1" autocomplete="off">
            </div>

            <details class="mb-3">
                <summary class="fw-semibold">Qué enviamos contigo</summary>
                <p class="text-secondary small mb-0 mt-2">
                    Junto a tu mensaje viaja tu nombre y correo de la cuenta, la versión de
                    Finlia, la pantalla desde la que reportaste, tu navegador y el tamaño de
                    la ventana. <strong>No enviamos tus movimientos, saldos ni cuentas.</strong>
                </p>
            </details>

            <button type="submit" class="btn btn-finlia w-100">Enviar reporte</button>
        </form>
    </div>

    <script>
        // Tamaño de ventana: dato del navegador, no del formulario.
        (function () {
            var campo = document.getElementById('reporte-viewport');
            if (campo) {
                campo.value = window.innerWidth + 'x' + window.innerHeight;
            }
        })();
    </script>
@endsection
