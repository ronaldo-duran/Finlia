<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Guías de pantalla — contenido (ADR-0045)
|--------------------------------------------------------------------------
| Este archivo es CONTENIDO, no lógica: es el guion de lo que la app le
| cuenta a quien entra por primera vez a cada pantalla. Se reescribe cada
| vez que haya una funcionalidad nueva que presentar. El motor que lo pinta
| vive en resources/js/tour.js y no hay que tocarlo para cambiar una guía.
|
| ── Cómo añadir una novedad a una guía que ya existe ──────────────────────
|
|  1. Sube el `version` de la guía (de 3 a 4, por ejemplo).
|  2. Añade los pasos nuevos con `'since' => 4`.
|  3. Listo. Quien nunca la vio recibe la guía completa; quien la vio en la
|     v3 recibe SOLO los pasos nuevos, presentados como «Novedades».
|
| Corolario: si solo corriges una errata o reescribes un texto, NO subas la
| versión — no hay nada nuevo que enseñarle a quien ya la vio.
|
| ── Campos de una guía ───────────────────────────────────────────────────
|
|  title    Nombre visible (menú del avatar y catálogo del perfil).
|  icon     Icono de Bootstrap Icons para el catálogo.
|  summary  Una línea: qué enseña. Solo se ve en /perfil.
|  route    Patrón de nombre de ruta donde vive ('debts.*', 'dashboard').
|  link     Ruta concreta a la que lleva el catálogo del perfil. No se
|           deriva del patrón a propósito: 'debts.*' no es una ruta.
|  version  Entero. Súbelo SOLO cuando añadas pasos.
|  auto     true = arranca sola la primera vez que se entra a la pantalla.
|           false = solo desde el menú del avatar o el catálogo del perfil.
|  steps    Los pasos, en orden.
|
| ── Campos de un paso ────────────────────────────────────────────────────
|
|  since    Versión de la guía en que nació el paso. Los pasos originales
|           llevan 1 y no se tocan nunca más. Un paso con `since` MAYOR
|           que el `version` de la guía está escrito pero sin publicar:
|           no lo ve nadie. Así puedes dejar redactada la guía de la
|           próxima entrega y publicarla luego subiendo la versión.
|  anchor   Selector CSS del elemento que ilumina, o null para un paso
|           centrado (intro y cierre). ¡Ojo! Si el elemento NO está en
|           pantalla —porque el usuario aún no tiene datos, o porque es la
|           barra de escritorio y está en el móvil—, el paso SE SALTA SOLO.
|           Eso es deliberado: así una guía nunca señala un vacío. Los pasos
|           que deben verse siempre van sin `anchor`.
|  title    Titular corto. Va como encabezado del globo.
|  body     Texto. Admite **negrita** y nada más — llega escapado al
|           navegador, así que no hay forma de colar HTML aquí.
|  list     Opcional: viñetas cortas. Mismo trato que `body`.
|
| Al añadir un `anchor` nuevo, marca el elemento en su Blade con
| data-tour="..." en vez de apoyarte en clases de Bootstrap: las clases
| cambian con cualquier retoque de maquetación y el paso se saltaría en
| silencio.
*/

return [

    /*
    |----------------------------------------------------------------------
    | Panel — la guía de bienvenida
    |----------------------------------------------------------------------
    | Es la única que se encuentra alguien recién registrado, así que carga
    | con explicar de qué va la app entera. Las demás dan por sabido esto.
    */
    'panel' => [
        'title' => 'El Panel',
        'icon' => 'bi-speedometer2',
        'summary' => 'De qué va Finlia, dónde está la cifra que importa y cómo se registra un movimiento.',
        'route' => 'dashboard',
        'link' => 'dashboard',
        'version' => 1,
        'auto' => true,
        'steps' => [
            [
                'since' => 1,
                'anchor' => null,
                'title' => 'Te presento Finlia 👋',
                'body' => 'Son **40 segundos**. Te muestro lo que hay en esta pantalla y dónde está cada cosa. Puedes salirte cuando quieras.',
            ],
            [
                'since' => 1,
                'anchor' => '[data-testid="available-money"]',
                'title' => 'La pregunta que responde Finlia',
                'body' => 'Esta cifra no es tu saldo: es **cuánto puedes gastar hoy sin quedar corto**. Descuenta lo que ya está comprometido antes de tu próximo ingreso.',
                'list' => [
                    'Tus gastos fijos y recurrentes',
                    'Las cuotas de tus deudas',
                    'Lo que le apartas a tus metas de ahorro',
                ],
            ],
            [
                'since' => 1,
                'anchor' => '[data-tour="panel-resumen"]',
                'title' => 'Cómo va el mes',
                'body' => 'Ingresos, gastos, saldo en cuentas, deuda y ahorro. Es el resumen corto; el detalle con gráficos está en **Reportes**.',
            ],
            [
                'since' => 1,
                'anchor' => '#fabContainer',
                'title' => 'Aquí se registra todo',
                'body' => 'Este botón **+** es la única entrada para registrar. Abre las cinco acciones: gasto, ingreso, transferencia, aporte a una meta y pago de deuda.',
            ],
            [
                'since' => 1,
                'anchor' => '.bottom-nav',
                'title' => 'Moverte por la app',
                'body' => 'Panel, Movimientos y Presupuesto siempre a mano. En **Más** está el resto: cuentas, deudas, metas, reportes y recordatorios.',
            ],
            [
                'since' => 1,
                'anchor' => '.finlia-sidebar',
                'title' => 'Moverte por la app',
                'body' => 'Todo lo que hace Finlia está en este menú. Cada pantalla tiene su propia guía la primera vez que entras.',
            ],
            [
                'since' => 1,
                'anchor' => null,
                'title' => 'Cuando quieras volver a verla',
                'body' => 'Las guías están en el **menú de tu avatar**, arriba a la derecha, y el listado completo en **Mi perfil → Guías de la app**. Ahí también se apagan de una vez.',
            ],
        ],
    ],

    /*
    |----------------------------------------------------------------------
    | Movimientos
    |----------------------------------------------------------------------
    */
    'movimientos' => [
        'title' => 'Movimientos',
        'icon' => 'bi-arrow-left-right',
        'summary' => 'Registrar gastos e ingresos, y encontrar uno viejo con los filtros.',
        'route' => 'movements.*',
        'link' => 'movements.index',
        'version' => 1,
        'auto' => true,
        'steps' => [
            [
                'since' => 1,
                'anchor' => null,
                'title' => 'Todo lo que entra y sale',
                'body' => 'Gastos, ingresos y transferencias entre tus cuentas, en una sola lista ordenada por día.',
            ],
            [
                'since' => 1,
                'anchor' => '[data-tour="movements-new"]',
                'title' => 'Registrar',
                'body' => 'Desde aquí o desde el botón **+**, da lo mismo. El registro toma diez segundos: monto, categoría y listo.',
            ],
            [
                'since' => 1,
                'anchor' => '[data-tour="movements-filters"]',
                'title' => 'Buscar sin dar vueltas',
                'body' => 'Los atajos filtran por tipo. En **Filtros** afinas por categoría, cuenta, miembro del hogar o rango de fechas — útil para responder "¿cuánto llevo en mercado este mes?".',
            ],
            [
                'since' => 1,
                'anchor' => '[data-tour="movements-list"]',
                'title' => 'Corregir lo que sea',
                'body' => 'Toca cualquier movimiento para editarlo o borrarlo. Registrar de más no es problema: aquí se arregla.',
            ],
        ],
    ],

    /*
    |----------------------------------------------------------------------
    | Presupuestos
    |----------------------------------------------------------------------
    */
    'presupuestos' => [
        'title' => 'Presupuestos',
        'icon' => 'bi-cash-stack',
        'summary' => 'Ponerle techo a una categoría y entender el aviso del 80 %.',
        'route' => 'budgets.*',
        'link' => 'budgets.index',
        'version' => 1,
        'auto' => true,
        'steps' => [
            [
                'since' => 1,
                'anchor' => null,
                'title' => 'Ponerle techo a lo que se desborda',
                'body' => 'Un presupuesto es un límite para una categoría: "máximo $600.000 en mercado este mes". Finlia avisa **antes** de que te lo pases, no después.',
            ],
            [
                'since' => 1,
                'anchor' => '[data-tour="budgets-period"]',
                'title' => 'Semana o mes',
                'body' => 'El arriendo se piensa por mes; el mercado, muchas veces por semana. Puedes tener presupuestos de los dos tipos a la vez.',
            ],
            [
                'since' => 1,
                'anchor' => '[data-tour="budgets-list"]',
                'title' => 'Las dos alertas',
                'body' => 'La barra se pone **amarilla al 80 %** y **roja al pasarse**. Esa misma alerta te aparece en el Panel, para que no tengas que venir a mirar.',
            ],
            [
                'since' => 1,
                'anchor' => null,
                'title' => 'Y afecta a lo que puedes gastar',
                'body' => 'Lo que presupuestas entra en el cálculo del Panel. Presupuestar de más no hace que tengas más plata: hace que Finlia te diga la verdad antes.',
            ],
        ],
    ],

    /*
    |----------------------------------------------------------------------
    | Gastos recurrentes
    |----------------------------------------------------------------------
    */
    'recurrentes' => [
        'title' => 'Gastos recurrentes',
        'icon' => 'bi-arrow-repeat',
        'summary' => 'Planificar lo que se repite y repartir los pagos anuales mes a mes.',
        'route' => 'recurring-expenses.*',
        'link' => 'recurring-expenses.index',
        'version' => 1,
        'auto' => true,
        'steps' => [
            [
                'since' => 1,
                'anchor' => null,
                'title' => 'Lo que ya sabes que llega',
                'body' => 'Arriendo, servicios, SOAT, colegio, suscripciones. Aquí **no se registran pagos**: se planifican. Registrarlos viene después, cuando se paguen.',
            ],
            [
                'since' => 1,
                'anchor' => '[data-tour="recurring-monthly"]',
                'title' => 'El truco de los pagos anuales',
                'body' => 'Un SOAT de $600.000 al año no es un golpe de $600.000: son **$50.000 al mes** que conviene ir separando. Finlia hace esa cuenta sola y la descuenta de lo que puedes gastar.',
            ],
            [
                'since' => 1,
                'anchor' => '[data-tour="recurring-upcoming"]',
                'title' => 'Qué se viene',
                'body' => 'Agrupado por urgencia: vencidas, esta semana y más adelante. Cuando pagues uno, **Marcar pagado** registra el gasto y adelanta la fecha al siguiente ciclo.',
            ],
        ],
    ],

    /*
    |----------------------------------------------------------------------
    | Deudas
    |----------------------------------------------------------------------
    */
    'deudas' => [
        'title' => 'Deudas',
        'icon' => 'bi-credit-card-2-front',
        'summary' => 'Ver cuánto te cuesta al mes, cuándo sales y en qué orden conviene pagar.',
        'route' => 'debts.*',
        'link' => 'debts.index',
        'version' => 1,
        'auto' => true,
        'steps' => [
            [
                'since' => 1,
                'anchor' => null,
                'title' => 'Tarjetas, préstamos y cuotas',
                'body' => 'Todo lo que debes en un solo sitio, con la fecha en la que terminas de pagarlo.',
            ],
            [
                'since' => 1,
                'anchor' => '[data-tour="debts-commitment"]',
                'title' => 'Lo que ya está comprometido',
                'body' => 'Esta plata **ya tiene dueño** cada mes. Sale del cálculo de cuánto puedes gastar, para que no cuentes dos veces con ella.',
            ],
            [
                'since' => 1,
                'anchor' => '[data-tour="debts-strategy"]',
                'title' => 'En qué orden pagar',
                'body' => 'Dos formas de ordenar la lista, y las dos son razonables:',
                'list' => [
                    'Avalancha: primero la de interés más alto. Pagas menos intereses.',
                    'Bola de nieve: primero la más pequeña. Ves una deuda cerrada antes y eso sostiene la motivación.',
                ],
            ],
            [
                'since' => 1,
                'anchor' => '[data-tour="debts-list"]',
                'title' => 'Registrar los pagos',
                'body' => 'Entra a una deuda para abonar. El saldo baja y la fecha de salida se recalcula. Las cifras son **estimaciones**: tu banco manda.',
            ],
        ],
    ],

    /*
    |----------------------------------------------------------------------
    | Metas de ahorro
    |----------------------------------------------------------------------
    */
    'metas' => [
        'title' => 'Metas de ahorro',
        'icon' => 'bi-piggy-bank',
        'summary' => 'Convertir un objetivo en una cuota mensual y seguirle el progreso.',
        'route' => 'savings-goals.*',
        'link' => 'savings-goals.index',
        'version' => 1,
        'auto' => true,
        'steps' => [
            [
                'since' => 1,
                'anchor' => null,
                'title' => 'De "algún día" a una cuota',
                'body' => 'Pones cuánto necesitas y para cuándo. Finlia calcula **cuánto toca apartar al mes** y lo descuenta de lo que puedes gastar, para que el ahorro no dependa de lo que sobre.',
            ],
            [
                'since' => 1,
                'anchor' => '[data-tour="savings-summary"]',
                'title' => 'Empieza por el fondo de emergencia',
                'body' => 'Si solo vas a tener una meta, que sea esa. Puedes marcarla como **fondo de emergencia** para distinguirla del resto.',
            ],
            [
                'since' => 1,
                'anchor' => '[data-tour="savings-list"]',
                'title' => 'Aportes y retiros',
                'body' => 'Entra a una meta para registrar lo que le abonas. Al llegar al objetivo se marca lograda sola. Los aportes **no mueven tus cuentas**: son el progreso de la meta.',
            ],
        ],
    ],

    /*
    |----------------------------------------------------------------------
    | Reportes
    |----------------------------------------------------------------------
    */
    'reportes' => [
        'title' => 'Reportes',
        'icon' => 'bi-bar-chart-line',
        'summary' => 'Comparar contra el período anterior, leer los hallazgos y exportar a Excel.',
        'route' => 'reports.*',
        'link' => 'reports.index',
        'version' => 1,
        'auto' => true,
        'steps' => [
            [
                'since' => 1,
                'anchor' => null,
                'title' => 'Mirar hacia atrás',
                'body' => 'El Panel responde "¿cómo voy hoy?". Reportes responde "¿cómo vengo?" — con la comparación contra el período anterior.',
            ],
            [
                'since' => 1,
                'anchor' => '[data-tour="reports-period"]',
                'title' => 'Comparaciones honestas',
                'body' => 'Cada período se compara contra **el mismo tramo** del anterior: si vas por el día 10, el año pasado también se corta el día 10. Comparar un mes a medias contra uno completo solo asusta.',
            ],
            [
                'since' => 1,
                'anchor' => '[data-tour="reports-insights"]',
                'title' => 'Hallazgos',
                'body' => 'Frases cortas sobre lo que cambió de verdad. No salen por cualquier variación: hay umbrales para que no se vuelvan ruido que dejes de leer.',
            ],
            [
                'since' => 1,
                'anchor' => '[data-tour="reports-export"]',
                'title' => 'Llevártelo a Excel',
                'body' => 'Exporta el período en CSV, listo para abrir en Excel en español. Tus datos son tuyos y salen de aquí cuando quieras.',
            ],
        ],
    ],

    /*
    |----------------------------------------------------------------------
    | Recordatorios
    |----------------------------------------------------------------------
    */
    'recordatorios' => [
        'title' => 'Recordatorios',
        'icon' => 'bi-bell',
        'summary' => 'Qué vence pronto, de dónde sale cada aviso y el resumen por correo.',
        'route' => 'reminders.*',
        'link' => 'reminders.index',
        'version' => 1,
        'auto' => true,
        'steps' => [
            [
                'since' => 1,
                'anchor' => null,
                'title' => 'Que no se te pase nada',
                'body' => 'Esta lista se arma sola con tus gastos recurrentes, las cuotas de tus deudas y las fechas de tus metas. No hay que cargarla a mano.',
            ],
            [
                'since' => 1,
                'anchor' => '[data-tour="reminders-list"]',
                'title' => 'Ordenado por urgencia',
                'body' => 'Vencidas primero, después lo de los próximos siete días. También puedes añadir avisos sueltos para lo que no vive en la app.',
            ],
            [
                'since' => 1,
                'anchor' => '[data-tour="reminders-email"]',
                'title' => 'El resumen por correo',
                'body' => 'Si lo activas, recibes un correo con lo que viene. Se apaga desde aquí o desde el propio correo, sin buscar nada.',
            ],
        ],
    ],

    /*
    |----------------------------------------------------------------------
    | Cuentas
    |----------------------------------------------------------------------
    */
    'cuentas' => [
        'title' => 'Cuentas',
        'icon' => 'bi-wallet',
        'summary' => 'Dónde está tu plata y por qué conviene registrarlo.',
        'route' => 'accounts.*',
        'link' => 'accounts.index',
        'version' => 1,
        'auto' => true,
        'steps' => [
            [
                'since' => 1,
                'anchor' => null,
                'title' => 'Dónde está tu plata',
                'body' => 'Banco, efectivo, billeteras como Nequi o Daviplata, y tarjetas de crédito. Cada movimiento sale de una cuenta o entra a una.',
            ],
            [
                'since' => 1,
                'anchor' => '[data-tour="accounts-list"]',
                'title' => 'Por qué importa el saldo',
                'body' => 'La suma de tus cuentas es el techo real de lo que puedes gastar hoy. Sin cuentas registradas, Finlia solo puede guiarse por tu plan del mes.',
            ],
            [
                'since' => 1,
                'anchor' => null,
                'title' => 'Tarjetas de crédito',
                'body' => 'Una tarjeta es una cuenta más, con su cupo y su fecha de corte. Finlia **nunca** te pide el número completo, el CVV ni el PIN — no hay dónde guardarlos.',
            ],
        ],
    ],

    /*
    |----------------------------------------------------------------------
    | Hogares
    |----------------------------------------------------------------------
    */
    'hogares' => [
        'title' => 'Hogares',
        'icon' => 'bi-house-heart',
        'summary' => 'Compartir finanzas con tu familia y separar lo personal de lo común.',
        'route' => 'households.*',
        'link' => 'households.index',
        'version' => 1,
        'auto' => true,
        'steps' => [
            [
                'since' => 1,
                'anchor' => null,
                'title' => 'Finanzas de a dos (o de a cinco)',
                'body' => 'Un hogar es un grupo de personas que comparten las mismas cuentas y los mismos gastos. Invitas a alguien y ve lo mismo que tú.',
            ],
            [
                'since' => 1,
                'anchor' => '[data-tour="households-list"]',
                'title' => 'Puedes tener varios',
                'body' => 'Uno para lo de la casa y otro para lo tuyo, por ejemplo. Los datos **no se mezclan nunca** entre hogares: cambias de hogar activo y la app entera cambia con él.',
            ],
            [
                'since' => 1,
                'anchor' => null,
                'title' => 'Invitar a alguien',
                'body' => 'Entra al hogar y envía la invitación por correo. Quien la recibe puede aceptarla aunque todavía no tenga cuenta en Finlia.',
            ],
        ],
    ],

];
