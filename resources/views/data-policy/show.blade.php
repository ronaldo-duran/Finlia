@extends('layouts.guest', ['title' => 'Tus datos y Finlia', 'subtitle' => 'Política de datos', 'width' => 720])

@section('content')
    <div class="text-center mb-4">
        <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-finlia-subtle mb-3"
             style="width:56px; height:56px;">
            <i class="bi bi-shield-check fs-4 text-finlia"></i>
        </div>
        <h1 class="h4 fw-bold mb-1">Tus datos y Finlia</h1>
        <p class="text-muted small mb-0">Última actualización: {{ \Carbon\Carbon::create(2026, 9, 1)->format('d/m/Y') }}</p>
    </div>

    <div class="alert alert-light border small mb-4" role="note">
        <i class="bi bi-info-circle me-1"></i>
        Este es un <strong>borrador</strong> de la política de datos de Finlia.
        El texto definitivo y los compromisos de negocio (ventana de aviso de retiro,
        rotación de copias de seguridad) serán confirmados por el equipo de Finlia
        antes de la versión de pago. <!-- COMPLETAR ANTES DE LANZAMIENTO COMERCIAL -->
    </div>

    <div class="text-body small">

        {{-- 1. Qué guardamos --}}
        <h2 class="h6 fw-bold mt-4 mb-2">1. Qué datos guardamos</h2>
        <p>
            Finlia guarda exclusivamente los datos que tú registras:
        </p>
        <ul>
            <li><strong>Cuenta:</strong> nombre, correo, fecha de nacimiento, región y género (estos dos últimos, opcionales). Nunca almacenamos tu número de tarjeta, CVV ni PIN — esas columnas sencillamente no existen.</li>
            <li><strong>Hogar:</strong> cuentas bancarias, movimientos de ingresos y gastos, presupuestos, gastos recurrentes, deudas, metas de ahorro, recordatorios y los miembros que invites.</li>
        </ul>
        <p>
            No vendemos ni compartimos tus datos con terceros. Los datos del hogar son visibles para todos sus miembros activos.
        </p>

        {{-- 2. Portabilidad --}}
        <h2 class="h6 fw-bold mt-4 mb-2">2. Portabilidad — descarga tus datos</h2>
        <p>
            Desde tu perfil (<a href="{{ route('profile.edit') }}">perfil → Exportar mis datos</a>) puedes descargar en cualquier momento un archivo ZIP con:
        </p>
        <ul>
            <li>Un CSV por entidad (cuentas, gastos, deudas, metas, etc.), apto para abrir en Excel sin configuración.</li>
            <li>Un archivo <code>finlia.json</code> con todos tus datos para migración técnica.</li>
            <li>Un <code>README.txt</code> que explica cada archivo y su formato.</li>
        </ul>
        <p>
            La exportación está acotada al hogar activo (si tienes varios, puedes cambiar de hogar y repetir). No incluye datos personales de otros miembros.
        </p>

        {{-- 3. Eliminación --}}
        <h2 class="h6 fw-bold mt-4 mb-2">3. Eliminación de tu cuenta</h2>
        <p>
            Puedes solicitar la eliminación desde tu perfil. Al hacerlo:
        </p>
        <ol>
            <li>Tu cuenta se <strong>suspende 30 días</strong> — puedes reactivarla iniciando sesión en ese plazo.</li>
            <li>Si no la reactivás, al cabo de 30 días <strong>tus datos personales se eliminan</strong> y el registro queda anonimizado.</li>
            <li>Si eras el único administrador de un hogar sin otros miembros, el hogar también se borra. Si había otros miembros, el hogar se transfiere al más antiguo y el historial financiero compartido se conserva (la historia del hogar no le pertenece solo a quien se va).</li>
        </ol>
        <p>
            Te enviamos un correo de confirmación con el plazo exacto. Puedes descargar tus datos antes de que venza.
        </p>

        {{-- 4. Retiro del software --}}
        <h2 class="h6 fw-bold mt-4 mb-2">4. Retiro del software</h2>
        <p>
            Si Finlia dejara de operar: <!-- COMPLETAR: ventana de aviso -->
        </p>
        <ul>
            <li>Avisamos con al menos <strong>[60 / 90] días</strong> de antelación por correo a todos los usuarios activos. <!-- COMPLETAR --></li>
            <li>Durante ese período, la exportación de datos permanece disponible.</li>
            <li>Transcurrido el plazo, los datos se eliminan conforme a la política de eliminación descrita arriba.</li>
        </ul>
        <p>
            Las copias de seguridad en Hostinger siguen la rotación estándar del plan contratado. <!-- COMPLETAR con política real de backups --></p>

        {{-- 5. Migración --}}
        <h2 class="h6 fw-bold mt-4 mb-2">5. Migración a otra herramienta</h2>
        <p>
            El formato de exportación está documentado en el <code>README.txt</code> de cada ZIP. Los CSV usan separador <code>;</code>, codificación UTF-8 con BOM, fechas <code>DD/MM/AAAA</code> y montos con coma decimal — compatible con Excel, LibreOffice Calc y Google Sheets. El <code>finlia.json</code> incluye la misma información en formato estructurado apto para importar en cualquier herramienta que soporte JSON.
        </p>

        {{-- Contacto --}}
        <h2 class="h6 fw-bold mt-4 mb-2">Preguntas</h2>
        <p>
            Si tienes dudas sobre tus datos, escríbenos a <!-- COMPLETAR: correo de contacto -->. <!-- COMPLETAR --></p>

    </div>

    <hr class="my-4">
    <p class="small text-muted text-center">
        <a href="{{ route('terms.show') }}" class="text-decoration-none">Términos y condiciones</a>
        &middot;
        <a href="{{ route('home') }}" class="text-decoration-none">Volver al inicio</a>
    </p>
@endsection
