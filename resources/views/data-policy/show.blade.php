@extends('layouts.guest', ['title' => 'Tus datos y Finlia', 'subtitle' => 'Política de datos', 'width' => 720])

@section('content')
    <div class="text-center mb-4">
        <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-finlia-subtle mb-3"
             style="width:56px; height:56px;">
            <i class="bi bi-shield-check fs-4 text-finlia"></i>
        </div>
        <h1 class="h4 fw-bold mb-1">Tus datos y Finlia</h1>
        <p class="text-muted small mb-0">Última actualización: {{ \Carbon\Carbon::create(2026, 9, 10)->format('d/m/Y') }}</p>
    </div>

    <div class="text-body small">

        <p>
            Esta política explica qué datos guarda Finlia, para qué, con quién se comparten y qué
            puedes hacer con ellos. Forma parte de los
            <a href="{{ route('terms.show') }}">términos y condiciones</a> y se rige por la
            <strong>Ley 1581 de 2012</strong>, el Decreto 1377 de 2013 y demás normas colombianas
            aplicables.
        </p>

        {{-- 1. Responsable --}}
        <h2 class="h6 fw-bold mt-4 mb-2">1. Quién responde por tus datos</h2>
        <p>
            El responsable del tratamiento es <strong>Ronaldo Durán</strong>, persona natural
            domiciliada en Colombia, que opera Finlia en <code>finlia.online</code> y
            <code>app.finlia.online</code>. Canal de atención:
            <a href="mailto:contacto@finlia.online">contacto@finlia.online</a> y el
            <a href="{{ route('contact.create') }}">formulario de contacto</a>.
        </p>

        {{-- 2. Qué guardamos --}}
        <h2 class="h6 fw-bold mt-4 mb-2">2. Qué datos guardamos</h2>
        <p>
            Finlia guarda exclusivamente los datos que tú registras:
        </p>
        <ul>
            <li><strong>Cuenta:</strong> nombre, correo, fecha de nacimiento, región y género (estos dos últimos, opcionales). Nunca almacenamos tu número de tarjeta, CVV ni PIN — esas columnas sencillamente no existen.</li>
            <li><strong>Hogar:</strong> cuentas bancarias, movimientos de ingresos y gastos, presupuestos, gastos recurrentes, deudas, metas de ahorro, recordatorios y los miembros que invites.</li>
            <li><strong>Prueba de consentimiento:</strong> la versión de los términos que aceptaste, la fecha y la dirección IP desde la que lo hiciste. Se guarda con esa única finalidad, porque la ley nos exige poder demostrar que la autorización existió.</li>
            <li><strong>Mensajes que nos escribes:</strong> si nos contactas o reportas un error, guardamos tu nombre, tu correo y lo que escribiste. En un reporte de error se adjunta además el contexto técnico —versión de Finlia, pantalla desde la que reportaste, navegador y tamaño de ventana— para poder reproducir el problema; <strong>nunca tus movimientos, saldos ni cuentas</strong>. Si escribes desde el sitio público <strong>sin haber iniciado sesión</strong>, guardamos también la dirección IP del envío, con la única finalidad de frenar el abuso del formulario: cuando hay sesión no se guarda, porque ya sabemos quién eres.</li>
        </ul>
        <p>
            <strong>Finlia no se conecta a tu banco</strong> y nunca te pedirá las claves de tus
            cuentas bancarias. Todo lo que hay en la aplicación lo escribiste tú.
        </p>

        {{-- 3. Finalidades --}}
        <h2 class="h6 fw-bold mt-4 mb-2">3. Para qué los usamos</h2>
        <p>
            Tus datos se usan únicamente para:
        </p>
        <ul>
            <li>Prestarte el servicio: registrar tus movimientos y calcular tus presupuestos, deudas, metas y el dinero disponible.</li>
            <li>Identificarte y mantener tu sesión segura.</li>
            <li>Enviarte los correos imprescindibles de la cuenta: verificación, recuperación de contraseña, avisos de cambios de seguridad e invitaciones a un hogar.</li>
            <li>Enviarte el resumen diario de obligaciones, <strong>solo si lo activaste</strong>. Se desactiva desde la propia aplicación o desde el enlace de baja de cualquiera de esos correos.</li>
            <li>Atender lo que nos escribas y corregir errores que reportes.</li>
            <li>Cumplir obligaciones legales.</li>
        </ul>
        <p>
            <strong>No usamos tus datos para publicidad</strong>, no elaboramos perfiles para
            venderlos y no los cedemos a terceros con fines comerciales.
        </p>

        {{-- 4. Encargados --}}
        <h2 class="h6 fw-bold mt-4 mb-2">4. Con quién se comparten</h2>
        <p>
            No vendemos ni compartimos tus datos con terceros con fines comerciales. Para que el
            servicio funcione se apoya en dos proveedores, que actúan como encargados del
            tratamiento y solo pueden usar los datos para prestarnos su servicio:
        </p>
        <ul>
            <li><strong>Hostinger</strong> — alojamiento de la aplicación y de la base de datos. Los servidores que atienden a Finlia están en Brasil.</li>
            <li><strong>Brevo</strong> — envío de los correos transaccionales. Solo recibe la dirección de destino y el contenido del correo; nunca tus movimientos, saldos ni cuentas.</li>
        </ul>
        <p>
            Que los servidores estén fuera de Colombia implica una transferencia internacional de
            datos, que autorizas al aceptar esta política. Si un juez o una autoridad competente
            requiere información mediante orden legal, estamos obligados a entregarla.
        </p>
        <p>
            Dentro de un hogar, <strong>los datos son visibles para todos sus miembros activos</strong>.
            Ningún usuario puede ver los datos de un hogar al que no pertenece.
        </p>

        {{-- 5. Derechos --}}
        <h2 class="h6 fw-bold mt-4 mb-2">5. Tus derechos y cómo ejercerlos</h2>
        <p>
            Como titular de los datos tienes derecho a conocerlos, actualizarlos y rectificarlos;
            a solicitar prueba de la autorización que diste; a ser informado sobre el uso que se
            les ha dado; a revocar la autorización y pedir que se supriman cuando no exista un
            deber legal de conservarlos; a acceder gratuitamente a ellos; y a presentar quejas ante
            la <strong>Superintendencia de Industria y Comercio</strong>.
        </p>
        <p>
            Buena parte los puedes ejercer tú mismo y al instante desde
            <a href="{{ route('profile.edit') }}">tu perfil</a>: corregir tus datos, descargarlos
            completos o eliminar tu cuenta. Para lo demás, escríbenos a
            <a href="mailto:contacto@finlia.online">contacto@finlia.online</a>.
        </p>
        <p>
            Las <strong>consultas</strong> se atienden en un máximo de <strong>diez (10) días
            hábiles</strong>, prorrogables por cinco (5) más. Los <strong>reclamos</strong>, en
            <strong>quince (15) días hábiles</strong>, prorrogables por ocho (8). Si hace falta la
            prórroga, te informamos el motivo y la fecha en que responderemos.
        </p>

        {{-- 6. Portabilidad --}}
        <h2 class="h6 fw-bold mt-4 mb-2">6. Portabilidad — descarga tus datos</h2>
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

        {{-- 7. Eliminación --}}
        <h2 class="h6 fw-bold mt-4 mb-2">7. Eliminación de tu cuenta</h2>
        <p>
            Puedes solicitar la eliminación desde tu perfil. Al hacerlo:
        </p>
        <ol>
            <li>Tu cuenta se <strong>suspende 30 días</strong> — puedes reactivarla iniciando sesión en ese plazo.</li>
            <li>Si no la reactivas, al cabo de 30 días <strong>tus datos personales se eliminan</strong> y el registro queda anonimizado.</li>
            <li>Si eras el único administrador de un hogar sin otros miembros, el hogar también se borra. Si había otros miembros, el hogar se transfiere al más antiguo y el historial financiero compartido se conserva (la historia del hogar no le pertenece solo a quien se va).</li>
        </ol>
        <p>
            Te enviamos un correo de confirmación con el plazo exacto. Puedes descargar tus datos antes de que venza.
        </p>
        <p>
            Se conserva únicamente lo que la ley obliga a conservar, como la prueba del
            consentimiento, y por el tiempo que esa obligación exija.
        </p>

        {{-- 8. Seguridad --}}
        <h2 class="h6 fw-bold mt-4 mb-2">8. Cómo protegemos tus datos</h2>
        <ul>
            <li>Todo el tráfico viaja cifrado por HTTPS.</li>
            <li>Las contraseñas se guardan con un algoritmo de hash irreversible: <strong>nadie en Finlia puede leer la tuya</strong>, ni siquiera nosotros.</li>
            <li>Cada consulta a datos financieros está acotada al hogar del usuario autenticado, y ese aislamiento se verifica automáticamente con cada cambio del código.</li>
            <li>Cambiar tu contraseña cierra las sesiones abiertas en otros dispositivos.</li>
            <li>El código es <a href="https://github.com/ronaldo-duran/Finlia" rel="noopener">público y auditable</a>: cualquiera puede revisar cómo se tratan los datos en lugar de creerse esta página.</li>
        </ul>
        <p>
            Ningún sistema es infalible. Si ocurriera un incidente que afecte a tus datos
            personales, te lo comunicaremos y lo reportaremos a la autoridad conforme a la ley.
        </p>

        {{-- 9. Retiro del software --}}
        <h2 class="h6 fw-bold mt-4 mb-2">9. Si Finlia dejara de operar</h2>
        <ul>
            <li>Avisamos con al menos <strong>90 días</strong> de antelación por correo a todos los usuarios activos.</li>
            <li>Durante ese período, la exportación de datos permanece disponible.</li>
            <li>Transcurrido el plazo, los datos se eliminan conforme a la política de eliminación descrita arriba.</li>
        </ul>
        <p>
            Las copias de seguridad son las que realiza automáticamente el proveedor de alojamiento
            y se sobrescriben en su propio ciclo de rotación. Por eso <strong>no son un archivo
            histórico</strong> ni un sustituto de tu exportación: si quieres conservar tu
            información, descárgala tú.
        </p>

        {{-- 10. Migración --}}
        <h2 class="h6 fw-bold mt-4 mb-2">10. Migración a otra herramienta</h2>
        <p>
            El formato de exportación está documentado en el <code>README.txt</code> de cada ZIP. Los CSV usan separador <code>;</code>, codificación UTF-8 con BOM, fechas <code>DD/MM/AAAA</code> y montos con coma decimal — compatible con Excel, LibreOffice Calc y Google Sheets. El <code>finlia.json</code> incluye la misma información en formato estructurado apto para importar en cualquier herramienta que soporte JSON.
        </p>

        {{-- 11. Cambios --}}
        <h2 class="h6 fw-bold mt-4 mb-2">11. Cambios en esta política</h2>
        <p>
            Si cambiamos esta política actualizaremos la fecha del encabezado. Cuando el cambio
            afecte a las finalidades del tratamiento o a con quién se comparten tus datos, te lo
            comunicaremos y te pediremos aceptar la versión nueva de los términos antes de seguir
            usando la aplicación.
        </p>

        {{-- Contacto --}}
        <h2 class="h6 fw-bold mt-4 mb-2">Preguntas</h2>
        <p>
            Si tienes dudas sobre tus datos, escríbenos a
            <a href="mailto:contacto@finlia.online">contacto@finlia.online</a> o desde el
            <a href="{{ route('contact.create') }}">formulario de contacto</a>. Respondemos.
        </p>

    </div>

    <hr class="my-4">
    <p class="small text-muted text-center">
        <a href="{{ route('terms.show') }}" class="text-decoration-none">Términos y condiciones</a>
        &middot;
        <a href="{{ route('home') }}" class="text-decoration-none">Volver al inicio</a>
    </p>
@endsection
