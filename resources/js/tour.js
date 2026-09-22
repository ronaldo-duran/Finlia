const source = document.getElementById('finlia-tour-data');
const data = source ? JSON.parse(source.textContent) : null;
if (data && data.guide && Array.isArray(data.guide.steps)) {
    iniciar(data);
}
function iniciar(config) {
    const guia = config.guide;
    const sinAnimacion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const CLAVE_LOCAL = 'finlia.tour.' + guia.key;
    function vistaLocalmente() {
        try {
            return parseInt(localStorage.getItem(CLAVE_LOCAL) || '0', 10) >= guia.version;
        } catch (e) {
            return false;
        }
    }

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
            .filter((paso) => completa || paso.since > guia.seen)
            .map((paso) => ({ paso: paso, el: elementoVisible(paso.anchor) }))
            .filter((par) => !par.paso.anchor || par.el !== null);
    }

    let pasos = [];
    let indice = 0;
    let abierto = false;
    let esNovedad = false;
    let rafPendiente = 0;
    let focoPrevio = null;

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
        botonSilenciar.hidden = indice !== 0;

        const destacado = actual.paso.anchor ? document.querySelector(actual.paso.anchor) : null;
        if (destacado) {
            const r = destacado.getBoundingClientRect();
            if (r.height > window.innerHeight * 0.8) {
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
    const HOLGURA = 8;
    const SEPARACION = 12;
    const MARGEN = 12;
    const ALTO_CABECERA = 96;
    function esMovil() {
        return window.innerWidth < 576;
    }
    function colocar() {
        const actual = pasos[indice];
        if (!actual) return;
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
        capa.classList.toggle('tour-sin-foco', !iluminado);
        if (esMovil()) {
            globo.classList.add('tour-hoja');
            globo.style.top = '';
            globo.style.left = '';
            globo.style.width = '';
            const alto = globo.offsetHeight;
            const tapaAbajo = iluminado && r.bottom > window.innerHeight - alto - SEPARACION;
            const tapaArriba = iluminado && r.top < alto + SEPARACION;
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
        }
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
    function alEntrarFoco(e) {
        if (abierto && capa && !capa.contains(e.target)) {
            globo.focus({ preventScroll: true });
        }
    }
    function avisar(estado) {
        try {
            localStorage.setItem(CLAVE_LOCAL, String(guia.version));
        } catch (e) {  }
        const token = document.querySelector('meta[name="csrf-token"]');
        if (!token || !config.urls || !config.urls.seen) return;
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
        }).catch(() => {  });
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
    function abrir(completa) {
        if (abierto) return;
        document.body.classList.add('tour-abierto');
        pasos = pasosPara(completa);
        if (pasos.length === 0) {
            document.body.classList.remove('tour-abierto');
            return;
        }

        if (!capa) construir();
        esNovedad = !completa && guia.seen > 0;
        indice = 0;
        abierto = true;
        focoPrevio = document.activeElement;
        capa.classList.add('tour-visible');
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

    window.FinliaTour = {
        key: guia.key,
        abrir: () => abrir(true),
        cerrar: () => cerrar('skipped'),
    };
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
