@php
    // Una sola fuente para el acordeón y para los datos estructurados: si se
    // escriben dos veces, tarde o temprano dicen cosas distintas y el buscador
    // acaba mostrando una respuesta que la página ya no da.
    $preguntas = [
        [
            'p' => '¿Finlia se conecta a mi banco?',
            'r' => 'No. Los movimientos los registras tú, y por eso Finlia nunca pide las claves de tu banco ni accede a tus cuentas. A cambio de escribir el gasto, ves de verdad en qué se te va la plata.',
        ],
        [
            'p' => '¿Y si todavía no me han pagado?',
            'r' => 'Finlia solo cuenta la plata que ya tienes. Lo que esperas recibir sirve para saber hasta qué día te tiene que alcanzar, pero no se suma hasta que lo registras. Si el pago se atrasa, la cifra no te miente: te avisa y sigue contando solo con lo que hay.',
        ],
        [
            'p' => '¿Tengo que usarla un tiempo antes de que sirva?',
            'r' => 'No. Con el saldo de tus cuentas y el día en que te pagan, la cifra es correcta desde el primer día. No necesita meses de historial para "estabilizarse".',
        ],
        [
            'p' => '¿Cuánto cuesta?',
            'r' => 'Finlia es gratis. Registra gastos e ingresos, controla deudas, arma presupuestos y define metas de ahorro sin pagar nada. Más adelante habrá funciones avanzadas de pago, pero lo que hoy funciona seguirá siendo gratuito.',
        ],
        [
            'p' => '¿Sirve para toda la familia?',
            'r' => 'Sí. Un hogar puede tener varios miembros, cada uno con su cuenta y su clave. Todos ven los mismos gastos, ingresos y metas del hogar, y nadie ve los de otro hogar.',
        ],
        [
            'p' => '¿Necesito instalar algo?',
            'r' => 'No. Finlia funciona en el navegador del celular y del computador. Si quieres, puedes instalarla desde el navegador y queda con su icono en la pantalla de inicio, como cualquier otra aplicación.',
        ],
        [
            'p' => '¿Qué pasa con mis datos?',
            'r' => 'Son tuyos. Puedes descargarlos completos cuando quieras y puedes eliminar tu cuenta en cualquier momento. Finlia no vende información a terceros.',
        ],
        [
            'p' => '¿Funciona en pesos colombianos?',
            'r' => 'Sí, Finlia está pensada para Colombia: pesos, fechas en DD/MM/AAAA y todo en español.',
        ],
    ];

    $title = 'Finlia — Finanzas personales y familiares';
    $description = 'Finlia te dice cuánto dinero puedes gastar hoy sin comprometer el arriendo, las cuotas ni tus metas. Gastos, ingresos, deudas y ahorro de tu hogar, desde el celular. Gratis y en pesos colombianos.';

    $schema = [
        '@context' => 'https://schema.org',
        '@graph' => [
            [
                '@type' => 'SoftwareApplication',
                'name' => 'Finlia',
                'applicationCategory' => 'FinanceApplication',
                'operatingSystem' => 'Web, Android, iOS',
                'url' => route('home'),
                'inLanguage' => 'es-CO',
                'description' => $description,
                'featureList' => [
                    'Cálculo de dinero disponible',
                    'Registro de gastos e ingresos',
                    'Presupuestos por categoría',
                    'Control de deudas y tarjetas de crédito',
                    'Metas de ahorro',
                    'Gastos recurrentes y recordatorios',
                    'Hogares compartidos entre varios miembros',
                    'Reportes con gráficos',
                ],
                'offers' => [
                    '@type' => 'Offer',
                    'price' => '0',
                    'priceCurrency' => 'COP',
                    'description' => 'Plan gratuito con todas las funciones básicas',
                ],
            ],
            [
                '@type' => 'FAQPage',
                'mainEntity' => array_map(fn (array $f) => [
                    '@type' => 'Question',
                    'name' => $f['p'],
                    'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['r']],
                ], $preguntas),
            ],
        ],
    ];
@endphp

@extends('marketing.layout', ['title' => $title, 'description' => $description, 'schema' => $schema])

@section('content')

    {{-- ------------------------------------------------------------------ Hero --}}
    <section class="hero-marketing">
        <div class="container">
            {{-- g-4 y no g-5: con gutter de 3rem el margen negativo de la fila (-24px)
                 supera al padding del .container (12px) y la página desborda a lo
                 ancho en móvil. El aire de escritorio lo pone el padding de la sección. --}}
            <div class="row align-items-center g-4 g-lg-5 gx-lg-5">
                <div class="col-12 col-lg-7">
                    <p class="etiqueta-seccion">Finanzas personales y familiares</p>

                    <h1 class="titular">
                        ¿Cuánto puedes gastar hoy
                        <span class="resaltado">sin quedar&nbsp;mal a fin de mes?</span>
                    </h1>

                    <p class="entradilla">
                        El saldo del banco no lo sabe. No sabe que el arriendo sale el día 5,
                        que la cuota de la tarjeta ya está comprometida, ni que estás juntando
                        para el viaje. <strong>Finlia sí.</strong>
                    </p>

                    <div class="d-flex flex-wrap gap-3 mb-4">
                        <a href="{{ route('register') }}" class="btn btn-finlia btn-lg px-4">
                            Empezar gratis
                        </a>
                        <a href="#como-funciona" class="btn btn-outline-finlia btn-lg px-4">
                            Ver cómo funciona
                        </a>
                    </div>

                    <ul class="lista-ventajas list-unstyled mb-0">
                        <li><i class="bi bi-check-circle-fill"></i> Gratis, sin tarjeta de crédito</li>
                        <li><i class="bi bi-check-circle-fill"></i> No pedimos las claves de tu banco</li>
                        <li><i class="bi bi-check-circle-fill"></i> En pesos y en español</li>
                    </ul>
                </div>

                {{-- Solo en escritorio: en un teléfono, la foto de un teléfono no
                     añade nada y empuja el contenido real fuera de la primera
                     pantalla. La prueba visual la da la tira de capturas. --}}
                <div class="col-lg-5 d-none d-lg-block">
                    <div class="marco-telefono">
                        {{-- Presupuestos y no el Panel: esta pantalla abre con la
                             cifra que promete la landing, mientras que el Panel
                             abre con dos avisos de obligaciones próximas — dos
                             alarmas apiladas contradicen el mensaje de la página. --}}
                        <img src="{{ asset('img/capturas/dinero-disponible.png') }}"
                             width="390" height="844" loading="lazy"
                             alt="Finlia en un celular, mostrando cuánto dinero queda disponible para los días que restan del mes, junto a lo gastado y lo ya comprometido.">
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- -------------------------------------------------------------- Problema --}}
    <section class="seccion seccion-alterna">
        <div class="container">
            <div class="row justify-content-center text-center">
                <div class="col-12 col-lg-8">
                    <h2 class="titulo-seccion">Tener plata en la cuenta no es lo mismo que poder gastarla</h2>
                    <p class="texto-seccion">
                        Uno mira el saldo, ve un número que se siente bien y compra. Tres semanas
                        después llegan el arriendo, la cuota y los servicios, y no cuadra. No es
                        que hayas gastado de más: es que ese número nunca fue tuyo del todo.
                    </p>
                </div>
            </div>

            <div class="row g-4 mt-2">
                @foreach ([
                    ['bi-calendar-x', 'Lo que ya tiene dueño', 'Arriendo, servicios, matrículas y seguros que todavía no se han cobrado, pero se van a cobrar.'],
                    ['bi-credit-card-2-front', 'Las cuotas del mes', 'La tarjeta y los créditos que salen sí o sí, estén o no en tu cabeza.'],
                    ['bi-piggy-bank', 'Lo que estás juntando', 'El ahorro que te propusiste, que deja de existir si se lo come el día a día.'],
                ] as [$icono, $tituloTarjeta, $textoTarjeta])
                    <div class="col-12 col-md-4">
                        <div class="card h-100 p-4">
                            <i class="bi {{ $icono }} icono-tarjeta"></i>
                            <h3 class="h6 fw-semibold mt-3 mb-2">{{ $tituloTarjeta }}</h3>
                            <p class="text-secondary mb-0 small">{{ $textoTarjeta }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ---------------------------------------------------------- Cómo funciona --}}
    <section id="como-funciona" class="seccion">
        <div class="container">
            <div class="text-center mb-5">
                <p class="etiqueta-seccion">Cómo funciona</p>
                <h2 class="titulo-seccion">Tres pasos, y ya sabes a qué atenerte</h2>
            </div>

            <div class="row g-4">
                @foreach ([
                    ['1', 'Dinos qué tienes y cuándo te pagan', 'El saldo de tus cuentas hoy y el día en que te llega la plata. Con eso la cifra es real desde el primer día, sin meses de historial.'],
                    ['2', 'Anota lo que gastas', 'Menos de cinco segundos por gasto, desde el celular, con el botón «+» siempre a mano.'],
                    ['3', 'Mira cuánto puedes gastar hoy', 'Finlia aparta lo que vence antes de tu próximo pago y reparte el resto en los días que faltan.'],
                ] as [$numero, $tituloPaso, $textoPaso])
                    <div class="col-12 col-md-4">
                        <div class="paso h-100">
                            <span class="numero-paso">{{ $numero }}</span>
                            <h3 class="h5 fw-semibold mb-2">{{ $tituloPaso }}</h3>
                            <p class="text-secondary mb-0">{{ $textoPaso }}</p>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Resta vertical y no en línea: es como se hace una cuenta en
                 papel, y en una fila las cifras no se pueden comparar de un
                 vistazo.

                 Los importes son redondos y a propósito NO se atan a los de la
                 captura: la demo se resiembra con historial aleatorio y con la
                 fecha del día, así que cualquier cifra que copiara de ahí
                 quedaría descuadrada al siguiente `migrate:fresh --seed`.

                 Parte del saldo, no de los ingresos esperados (ADR-0040): el
                 sueldo que aún no llega no se puede gastar. --}}
            <div class="formula-card mt-5">
                <p class="rotulo-formula">La cuenta que hace Finlia</p>

                <dl class="cuenta">
                    <div class="linea">
                        <dt>Saldo en tus cuentas hoy</dt>
                        <dd>$ 1.700.000</dd>
                    </div>
                    <div class="linea">
                        <dt>
                            <span class="signo" aria-hidden="true">−</span> Lo que ya tiene dueño
                            <small>arriendo, servicios y cuotas que vencen antes de tu próximo pago, y lo apartado para tus metas</small>
                        </dt>
                        <dd>$ 1.250.000</dd>
                    </div>
                    <div class="linea">
                        <dt><span class="signo" aria-hidden="true">=</span> Te queda hasta el día de pago</dt>
                        <dd>$ 450.000</dd>
                    </div>
                    <div class="linea">
                        <dt><span class="signo" aria-hidden="true">÷</span> Días que faltan para tu pago</dt>
                        <dd>9</dd>
                    </div>
                    <div class="linea total">
                        <dt>Puedes gastar hoy</dt>
                        <dd>$ 50.000</dd>
                    </div>
                </dl>

                <p class="nota-cuenta mb-0">
                    Ejemplo con cifras de demostración. Lo que esperas cobrar no se suma
                    hasta que llega: si el pago se atrasa, la cifra sigue siendo real.
                    Finlia rehace esta cuenta cada vez que registras algo.
                </p>
            </div>
        </div>
    </section>

    {{-- -------------------------------------------------------------- Funciones --}}
    <section id="funciones" class="seccion seccion-alterna">
        <div class="container">
            <div class="text-center mb-5">
                <p class="etiqueta-seccion">Funciones</p>
                <h2 class="titulo-seccion">Todo lo que mueve la plata de un hogar</h2>
            </div>

            <div class="row g-4">
                @foreach ([
                    ['bi-cash-coin', 'Gastos e ingresos', 'Registro rápido, categorías, cuentas y medios de pago. Y un historial que sí se entiende.'],
                    ['bi-pie-chart', 'Presupuestos', 'Un tope al mes, por categoría si quieres, con aviso antes de pasarte y no después.'],
                    ['bi-credit-card', 'Deudas y tarjetas', 'Cuánto debes, cuánto llevas pagado y cuándo terminas. Con estrategia avalancha o bola de nieve.'],
                    ['bi-piggy-bank-fill', 'Metas de ahorro', 'El viaje, el fondo de emergencia, la moto. Cuánto llevas y cuánto falta al mes.'],
                    ['bi-bell', 'Recordatorios', 'El SOAT, la tecnomecánica, la matrícula. Lo que se paga una vez al año y siempre sorprende.'],
                    ['bi-people', 'Hogares compartidos', 'Tú y quien vive contigo, viendo las mismas cuentas. Cada uno con su clave.'],
                ] as [$icono, $tituloFuncion, $textoFuncion])
                    <div class="col-12 col-sm-6 col-lg-4">
                        <div class="card h-100 p-4">
                            <i class="bi {{ $icono }} icono-tarjeta"></i>
                            <h3 class="h6 fw-semibold mt-3 mb-2">{{ $tituloFuncion }}</h3>
                            <p class="text-secondary mb-0 small">{{ $textoFuncion }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- --------------------------------------------------------------- Capturas --}}
    <section class="seccion">
        <div class="container">
            <div class="text-center mb-5">
                <p class="etiqueta-seccion">Por dentro</p>
                <h2 class="titulo-seccion">Pensada para el celular, no encogida para él</h2>
            </div>

            <div class="tira-capturas">
                @foreach ([
                    ['panel.png', 'Panel de Finlia con el saludo, las obligaciones próximas y el resumen del mes.', 'Tu mes de un vistazo'],
                    ['registrar-gasto.png', 'Formulario de Finlia para registrar un gasto en pocos segundos.', 'Registrar en segundos'],
                    ['reportes.png', 'Reportes de Finlia con gráficos de gastos por categoría e ingresos contra gastos.', 'Reportes claros'],
                    ['deudas.png', 'Panel de deudas de Finlia con el total, el pago mensual comprometido y el orden sugerido.', 'Deudas bajo control'],
                    ['metas.png', 'Metas de ahorro de Finlia con el progreso de cada objetivo.', 'Metas de ahorro'],
                ] as [$archivo, $alt, $pie])
                    <figure class="captura">
                        <img src="{{ asset('img/capturas/'.$archivo) }}" width="390" height="844" loading="lazy" alt="{{ $alt }}">
                        <figcaption>{{ $pie }}</figcaption>
                    </figure>
                @endforeach
            </div>
        </div>
    </section>

    {{-- -------------------------------------------------------------- Preguntas --}}
    <section id="preguntas" class="seccion seccion-alterna">
        <div class="container">
            <div class="text-center mb-5">
                <p class="etiqueta-seccion">Preguntas</p>
                <h2 class="titulo-seccion">Lo que todo el mundo pregunta</h2>
            </div>

            <div class="row justify-content-center">
                <div class="col-12 col-lg-8">
                    @foreach ($preguntas as $i => $faq)
                        <details class="faq" @if ($i === 0) open @endif>
                            <summary>{{ $faq['p'] }}</summary>
                            <p class="text-secondary mb-0">{{ $faq['r'] }}</p>
                        </details>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    {{-- ------------------------------------------------------------- CTA final --}}
    <section class="cta-final">
        <div class="container text-center">
            <h2 class="titulo-seccion mb-3">Empieza este mes</h2>
            <p class="texto-seccion mx-auto mb-4" style="max-width: 52ch;">
                Se tarda menos en crear la cuenta que en revisar el extracto del banco.
            </p>
            <a href="{{ route('register') }}" class="btn btn-finlia btn-lg px-5">Crear mi cuenta gratis</a>
            <p class="small text-secondary mt-3 mb-0">
                ¿Ya tienes cuenta? <a href="{{ route('login') }}" class="enlace-nav">Entrar</a>
            </p>
        </div>
    </section>

@endsection
