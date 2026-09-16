/*
|----------------------------------------------------------------------
| Finlia — Guías de pantalla (ADR-0045)
|----------------------------------------------------------------------
| El motor que pinta las guías. El CONTENIDO no está aquí: vive en
| config/tours.php y llega servido en un <script type="application/json"
| id="finlia-tour-data"> (layouts/partials/tour.blade.php). Para añadir o
| reescribir una guía no hay que tocar este archivo.
|
| Qué hace:
|   · ilumina un elemento de la página y explica para qué sirve;
|   · se salta solo los pasos cuyo elemento no está en pantalla — así una
|     guía nunca señala un vacío (sin datos aún) ni la barra de escritorio
|     desde un teléfono;
|   · en móvil el globo es una hoja inferior, y sube arriba cuando el
|     elemento iluminado está en la mitad de abajo (si no, lo taparía);
|   · avisa al servidor al terminar o al saltar, para no repetirse.
|
| Deliberadamente sin dependencias: Driver.js o Shepherd traen su propio
| aspecto y habría que repintarlo entero contra los tokens de
| docs/UI_DESIGN.md — más código del que hay aquí, y uno más que mantener.
*/

const source = document.getElementById('finlia-tour-data');
const data = source ? JSON.parse(source.textContent) : null;

if (data && data.guide && Array.isArray(data.guide.steps)) {
    iniciar(data);
}

function iniciar(config) {
    const guia = config.guide;
    const sinAnimacion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    // Espejo local del progreso. El servidor manda, pero si el fetch no sale
    // (modo privado, sin red, pestaña cerrada a medias) esto evita que la
    // misma guía reaparezca en bucle en este navegador.
    const CLAVE_LOCAL = 'finlia.tour.' + guia.key;

    function vistaLocalmente() {
        try {
            return parseInt(localStorage.getItem(CLAVE_LOCAL) || '0', 10) >= guia.version;
        } catch (e) {
            return false;
        }
    }

    // ---------------------------------------------------------------- pasos

    // Un paso sin `anchor` es un paso centrado (intro, cierre) y siempre vale.
    // Con `anchor`, solo vale si el elemento existe Y se ve: `display:none`
    // da un rectángulo de 0×0, y el menú lateral en móvil es un offcanvas
    // cerrado (visibility:hidden) que sí mide.
    function elementoVisible(selector) {
        if (!selector) return null;

        const el = document.querySelector(selector);
        if (!el) return null;

        const r = el.getBoundingClientRect();
        if (r.width === 0 && r.height === 0) return null;

        const estilo = getComputedStyle(el);
        if (estilo.visibility === 'hidden' || estilo.display === 'none' || estilo.opacity === '0') return null;

        return el;
    }

    function pasosPara(completa) {
        return guia.steps
            // `completa` = la pidió la persona: se ve entera. Si arranca sola,
            // solo lo que nació después de la versión que ya vio (novedades).
            .filter((paso) => completa || paso.since > guia.seen)
            .map((paso) => ({ paso: paso, el: elementoVisible(paso.anchor) }))
            .filter((par) => !par.paso.anchor || par.el !== null);
    }

    // --------------------------------------------------------------- estado

    let pasos = [];
    let indice = 0;
    let abierto = false;
    let esNovedad = false;
    let rafPendiente = 0;
    let focoPrevio = null;

    // ------------------------------------------------------------------ DOM

    let capa = null;
    let fondo = null;
    let foco = null;
    let globo = null;
    let etiqueta = null;
    let progreso = null;
    let titulo = null;
    let cuerpo = null;
    let lista = null;
    let botonAtras = null;
    let botonSiguiente = null;
    let botonSilenciar = null;

    function construir() {
        capa = document.createElement('div');
        capa.className = 'tour-layer';
        capa.setAttribute('role', 'dialog');
        capa.setAttribute('aria-modal', 'true');
        capa.setAttribute('aria-labelledby', 'tourTitulo');
        if (sinAnimacion) capa.classList.add('tour-sin-animacion');

        // El fondo es quien captura los clics: el halo lleva pointer-events
        // none para que su sombra gigante no se coma la página entera.
        fondo = document.createElement('div');
        fondo.className = 'tour-fondo';
        fondo.addEventListener('click', () => cerrar('skipped'));

        foco = document.createElement('div');
        foco.className = 'tour-foco';

        globo = document.createElement('div');
        globo.className = 'tour-globo';
        globo.tabIndex = -1;

        const cabecera = document.createElement('div');
        cabecera.className = 'tour-cabecera';

        etiqueta = document.createElement('span');
        etiqueta.className = 'tour-etiqueta';

        progreso = document.createElement('span');
        progreso.className = 'tour-progreso';

        const cerrarBtn = document.createElement('button');
        cerrarBtn.type = 'button';
        cerrarBtn.className = 'tour-cerrar';
        cerrarBtn.setAttribute('aria-label', 'Saltar esta guía');
        cerrarBtn.addEventListener('click', () => cerrar('skipped'));

        const aspa = document.createElement('i');
        aspa.className = 'bi bi-x-lg';
        aspa.setAttribute('aria-hidden', 'true');
        cerrarBtn.appendChild(aspa);

        cabecera.append(etiqueta, progreso, cerrarBtn);

        titulo = document.createElement('h2');
        titulo.className = 'tour-titulo';
        titulo.id = 'tourTitulo';

        cuerpo = document.createElement('p');
        cuerpo.className = 'tour-cuerpo';

        lista = document.createElement('ul');
        lista.className = 'tour-lista';

        const pie = document.createElement('div');
        pie.className = 'tour-pie';

        botonSilenciar = document.createElement('button');
        botonSilenciar.type = 'button';
        botonSilenciar.className = 'tour-silenciar';
        botonSilenciar.textContent = 'No mostrar más guías';
        botonSilenciar.addEventListener('click', silenciar);

        const acciones = document.createElement('div');
        acciones.className = 'tour-acciones';

        botonAtras = document.createElement('button');
        botonAtras.type = 'button';
        botonAtras.className = 'btn btn-sm btn-outline-secondary';
        botonAtras.textContent = 'Atrás';
        botonAtras.addEventListener('click', () => ir(indice - 1));

        botonSiguiente = document.createElement('button');
        botonSiguiente.type = 'button';
        botonSiguiente.className = 'btn btn-sm btn-finlia';
        botonSiguiente.addEventListener('click', avanzar);

        acciones.append(botonAtras, botonSiguiente);
        pie.append(botonSilenciar, acciones);

        globo.append(cabecera, titulo, cuerpo, lista, pie);
        capa.append(fondo, foco, globo);
        document.body.appendChild(capa);
    }

    // ------------------------------------------------------------ contenido

    // Texto plano con **negrita** y nada más. Se arma con nodos de texto, así
    // que no hay innerHTML por el que colar marcado aunque alguien escriba
    // etiquetas en config/tours.php.
    function texto(bruto) {
        const trozos = String(bruto == null ? '' : bruto).split(/\*\*(.+?)\*\*/g);
        const fragmento = document.createDocumentFragment();

        trozos.forEach((trozo, i) => {
            if (trozo === '') return;

            if (i % 2 === 1) {
                const fuerte = document.createElement('strong');
                fuerte.textContent = trozo;
                fragmento.appendChild(fuerte);
            } else {
                fragmento.appendChild(document.createTextNode(trozo));
            }
        });

        return fragmento;
    }

    function pintar() {
        const actual = pasos[indice];
        const esUltimo = indice === pasos.length - 1;

        titulo.textContent = actual.paso.title;

        cuerpo.replaceChildren(texto(actual.paso.body));

        lista.replaceChildren();
        (actual.paso.list || []).forEach((punto) => {
            const li = document.createElement('li');
            li.replaceChildren(texto(punto));
            lista.appendChild(li);
        });
        lista.hidden = lista.childElementCount === 0;

        etiqueta.textContent = esNovedad ? 'Novedades' : guia.title;
        etiqueta.classList.toggle('es-novedad', esNovedad);

        progreso.textContent = pasos.length > 1 ? (indice + 1) + ' de ' + pasos.length : '';
        botonAtras.hidden = indice === 0;
        botonSiguiente.textContent = esUltimo ? 'Listo' : 'Siguiente';

        // Solo en el primer paso: quien ya decidió seguir no necesita que le
        // recuerden que puede apagarlas, y el pie queda más limpio.
        botonSilenciar.hidden = indice !== 0;

        const destacado = actual.paso.anchor ? document.querySelector(actual.paso.anchor) : null;
        if (destacado) {
            const r = destacado.getBoundingClientRect();

            if (r.height > window.innerHeight * 0.8) {
                // Un elemento más alto que la ventana no se puede centrar: al
                // centrarlo quedan fuera su principio y su final, y el halo se
                // vuelve un marco que no se ve por ningún lado. Se lleva su
                // borde superior a la vista, bajo la barra de navegación.
                window.scrollBy({
                    top: r.top - ALTO_CABECERA,
                    behavior: sinAnimacion ? 'auto' : 'smooth',
                });
            } else {
                destacado.scrollIntoView({
                    block: 'center',
                    inline: 'nearest',
                    behavior: sinAnimacion ? 'auto' : 'smooth',
                });
            }
        }

        colocar();
        globo.focus({ preventScroll: true });
    }

    // ----------------------------------------------------------- colocación

    const HOLGURA = 8;       // aire entre el elemento y el halo
    const SEPARACION = 12;   // aire entre el halo y el globo
    const MARGEN = 12;       // aire mínimo contra el borde de la pantalla
    const ALTO_CABECERA = 96; // la navbar pegajosa, que no debe tapar el halo

    function esMovil() {
        return window.innerWidth < 576;
    }

    function colocar() {
        const actual = pasos[indice];
        if (!actual) return;

        // El elemento puede haber cambiado de sitio (scroll, un acordeón que
        // se abrió) o desaparecido entre pasos: se vuelve a buscar.
        const el = actual.paso.anchor ? document.querySelector(actual.paso.anchor) : null;
        const r = el ? el.getBoundingClientRect() : null;
        const iluminado = r && (r.width > 0 || r.height > 0);

        if (iluminado) {
            foco.hidden = false;
            foco.style.top = (r.top - HOLGURA) + 'px';
            foco.style.left = (r.left - HOLGURA) + 'px';
            foco.style.width = (r.width + HOLGURA * 2) + 'px';
            foco.style.height = (r.height + HOLGURA * 2) + 'px';
        } else {
            foco.hidden = true;
        }

        // Sin halo, el oscurecido lo pinta el fondo (ver .tour-sin-foco).
        capa.classList.toggle('tour-sin-foco', !iluminado);

        if (esMovil()) {
            globo.classList.add('tour-hoja');
            globo.style.top = '';
            globo.style.left = '';
            globo.style.width = '';

            // La hoja va abajo salvo que ahí tape justo lo que señala. No vale
            // mirar en qué mitad de la pantalla cae el elemento: el contenedor
            // del botón «+» incluye su menú desplegado y empieza por encima de
            // la mitad, aunque el botón esté pegado abajo. Lo que importa es si
            // se solapan, y eso se mide.
            const alto = globo.offsetHeight;
            const tapaAbajo = iluminado && r.bottom > window.innerHeight - alto - SEPARACION;
            const tapaArriba = iluminado && r.top < alto + SEPARACION;

            // Si el elemento es tan alto que estorba arriba y abajo, se queda
            // abajo: no hay opción mejor, solo la de siempre.
            globo.classList.toggle('tour-hoja-arriba', tapaAbajo && ! tapaArriba);

            return;
        }

        globo.classList.remove('tour-hoja', 'tour-hoja-arriba');

        const ancho = globo.offsetWidth;
        const alto = globo.offsetHeight;

        if (!iluminado) {
            globo.style.top = Math.max(MARGEN, (window.innerHeight - alto) / 2) + 'px';
            globo.style.left = Math.max(MARGEN, (window.innerWidth - ancho) / 2) + 'px';
            return;
        }

        // Debajo del elemento; si no cabe, encima; y si el elemento es más
        // alto que el hueco —una lista larga, por ejemplo— al lado: taparle a
        // la persona justo lo que se le está explicando es el peor resultado
        // posible de un tutorial.
        const debajo = r.bottom + HOLGURA + SEPARACION;
        const encima = r.top - HOLGURA - SEPARACION - alto;
        const cabeDebajo = debajo + alto <= window.innerHeight - MARGEN;
        const cabeEncima = encima >= MARGEN;

        let top = cabeDebajo ? debajo : encima;
        let left = r.left + r.width / 2 - ancho / 2;

        if (!cabeDebajo && !cabeEncima) {
            const derecha = r.right + HOLGURA + SEPARACION;
            const izquierda = r.left - HOLGURA - SEPARACION - ancho;

            top = r.top + r.height / 2 - alto / 2;

            if (derecha + ancho <= window.innerWidth - MARGEN) {
                left = derecha;
            } else if (izquierda >= MARGEN) {
                left = izquierda;
            }
            // Si tampoco hay hueco a los lados, se queda encima del elemento:
            // en una pantalla tan justa no hay buena colocación, solo la menos
            // mala. (En móvil no llega aquí: ahí es una hoja, más arriba.)
        }

        // Dentro de la pantalla pase lo que pase. El segundo Math.max cubre el
        // caso extremo de un globo más alto que la ventana.
        top = Math.min(Math.max(top, MARGEN), Math.max(MARGEN, window.innerHeight - alto - MARGEN));
        left = Math.min(Math.max(left, MARGEN), Math.max(MARGEN, window.innerWidth - ancho - MARGEN));

        globo.style.top = Math.round(top) + 'px';
        globo.style.left = Math.round(left) + 'px';
    }

    function recolocar() {
        if (!abierto || rafPendiente) return;
        rafPendiente = requestAnimationFrame(() => {
            rafPendiente = 0;
            colocar();
        });
    }

    // ------------------------------------------------------------ navegación

    function ir(destino) {
        if (destino < 0 || destino >= pasos.length) return;
        indice = destino;
        pintar();
    }

    function avanzar() {
        if (indice === pasos.length - 1) {
            cerrar('completed');
            return;
        }
        ir(indice + 1);
    }

    function alPulsarTecla(e) {
        if (!abierto) return;

        if (e.key === 'Escape') {
            e.preventDefault();
            cerrar('skipped');
        } else if (e.key === 'ArrowRight') {
            e.preventDefault();
            avanzar();
        } else if (e.key === 'ArrowLeft') {
            e.preventDefault();
            ir(indice - 1);
        }
    }

    // El foco no debe escaparse a la página de detrás mientras la guía está
    // abierta: se estaría tabulando por controles tapados por el fondo.
    function alEntrarFoco(e) {
        if (abierto && capa && !capa.contains(e.target)) {
            globo.focus({ preventScroll: true });
        }
    }

    // ------------------------------------------------------------- servidor

    function avisar(estado) {
        try {
            localStorage.setItem(CLAVE_LOCAL, String(guia.version));
        } catch (e) { /* modo privado: manda el servidor */ }

        const token = document.querySelector('meta[name="csrf-token"]');
        if (!token || !config.urls || !config.urls.seen) return;

        // keepalive: el último paso suele ir seguido de un clic en algún
        // enlace de la app, y sin esto el navegador cancelaría la petición.
        fetch(config.urls.seen, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': token.getAttribute('content'),
            },
            body: JSON.stringify({ status: estado }),
            credentials: 'same-origin',
            keepalive: true,
        }).catch(() => { /* el espejo local ya evita el bucle */ });
    }

    function silenciar() {
        const token = document.querySelector('meta[name="csrf-token"]');

        if (token && config.urls && config.urls.preference) {
            fetch(config.urls.preference, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': token.getAttribute('content'),
                },
                body: JSON.stringify({ enabled: false }),
                credentials: 'same-origin',
                keepalive: true,
            }).catch(() => {});
        }

        cerrar('skipped');
    }

    // ------------------------------------------------------- abrir / cerrar

    function abrir(completa) {
        if (abierto) return;

        pasos = pasosPara(completa);

        // Ni un paso con su elemento en pantalla: no se abre nada y no se da
        // por vista. Así la guía sigue esperando a que haya algo que enseñar.
        if (pasos.length === 0) return;

        if (!capa) construir();

        esNovedad = !completa && guia.seen > 0;
        indice = 0;
        abierto = true;
        focoPrevio = document.activeElement;

        capa.classList.add('tour-visible');
        document.body.classList.add('tour-abierto');

        document.addEventListener('keydown', alPulsarTecla);
        document.addEventListener('focusin', alEntrarFoco);
        window.addEventListener('resize', recolocar);
        window.addEventListener('scroll', recolocar, true);

        pintar();
    }

    function cerrar(estado) {
        if (!abierto) return;

        abierto = false;
        capa.classList.remove('tour-visible');
        document.body.classList.remove('tour-abierto');

        document.removeEventListener('keydown', alPulsarTecla);
        document.removeEventListener('focusin', alEntrarFoco);
        window.removeEventListener('resize', recolocar);
        window.removeEventListener('scroll', recolocar, true);

        if (focoPrevio && typeof focoPrevio.focus === 'function') {
            focoPrevio.focus({ preventScroll: true });
        }

        avisar(estado);
    }

    // --------------------------------------------------------------- pública

    // La usa el «Guía de esta pantalla» del menú del avatar. Siempre completa:
    // quien la pide a mano quiere verla entera, no solo lo nuevo.
    window.FinliaTour = {
        key: guia.key,
        abrir: () => abrir(true),
        cerrar: () => cerrar('skipped'),
    };

    // «Guía de esta pantalla», en el menú del avatar. Se engancha aquí y no
    // con un onclick en la vista: si el motor no llegó a cargar, el botón
    // simplemente no existe en vez de dar un error en consola al pulsarlo.
    document.querySelectorAll('[data-tour-open]').forEach((boton) => {
        boton.addEventListener('click', (e) => {
            e.preventDefault();
            abrir(true);
        });
    });

    if (config.start === 'open') {
        abrir(true);
    } else if (config.start === 'auto' && !vistaLocalmente()) {
        abrir(false);
    }
}
