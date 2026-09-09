/*
|----------------------------------------------------------------------
| Finlia — Punto de entrada JS
|----------------------------------------------------------------------
| Bootstrap 5 (bundle con Popper incluido) activa los componentes
| interactivos: dropdowns, offcanvas (menú móvil), collapse, etc.
| Además: toggle de tema claro/oscuro (persistente, sigue el SO si el
| usuario no eligió manualmente). Los formularios envían su token CSRF
| vía la directiva @csrf de Blade.
*/

// Se expone en `window` porque las vistas Blade instancian componentes a mano
// (bootstrap.Modal para el modal de confirmación, bootstrap.Toast para los
// mensajes flash). Un `import 'bootstrap'` a secas solo registra la data-api
// (data-bs-toggle) y dejaría `bootstrap` indefinido en los scripts inline.
import * as bootstrap from 'bootstrap';

window.bootstrap = bootstrap;

// Toggle de tema (claro/oscuro). El icono lo controla el CSS según
// el atributo data-bs-theme del <html>; aquí solo se conmuta y persiste.
(function () {
    var root = document.documentElement;
    var KEY = 'finlia-theme';

    function current() {
        return root.getAttribute('data-bs-theme') === 'dark' ? 'dark' : 'light';
    }

    function apply(t) {
        root.setAttribute('data-bs-theme', t);
        try { localStorage.setItem(KEY, t); } catch (e) {}
        document.querySelectorAll('meta[name="theme-color"]').forEach(function (m) {
            m.setAttribute('content', t === 'dark' ? '#0e1419' : '#eef3f8');
        });
    }

    document.querySelectorAll('[data-theme-toggle]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            apply(current() === 'dark' ? 'light' : 'dark');
        });
    });

    // Seguir el SO en vivo solo si el usuario no ha elegido manualmente.
    var mql = window.matchMedia('(prefers-color-scheme: dark)');
    mql.addEventListener('change', function (e) {
        if (!localStorage.getItem(KEY)) {
            apply(e.matches ? 'dark' : 'light');
        }
    });
})();

/*
|----------------------------------------------------------------------
| Formato de miles en vivo para inputs de dinero ([data-money-input]).
|----------------------------------------------------------------------
| Convención colombiana (docs/CONVENTIONS.md): punto de miles, coma
| decimal ("$ 1.234.567,50"). El input sigue siendo de texto (no
| type="number", que rechaza el punto de miles) pero conserva `required`
| y su validez nativa; justo antes de enviar el formulario se reescribe
| a un string numérico plano ("1234567.50") para que el Form Request
| (`numeric`) y el cast `decimal:2` lo validen sin tocarlos.
*/
window.FinliaMoney = (function () {
    function digitsAndDecimal(raw) {
        var cleaned = String(raw ?? '').replace(/[^\d,]/g, '');
        var commaIndex = cleaned.indexOf(',');
        var intPart = (commaIndex === -1 ? cleaned : cleaned.slice(0, commaIndex)).replace(/^0+(?=\d)/, '');
        var hasComma = commaIndex !== -1;
        var decPart = hasComma ? cleaned.slice(commaIndex + 1).replace(/,/g, '').slice(0, 2) : '';

        return { intPart: intPart, decPart: decPart, hasComma: hasComma };
    }

    // "12300" | "12.300,5" -> "12.300,5" (formato de pantalla).
    function format(raw) {
        var d = digitsAndDecimal(raw);
        var grouped = d.intPart.replace(/\B(?=(\d{3})+(?!\d))/g, '.');

        return d.hasComma ? (grouped || '0') + ',' + d.decPart : grouped;
    }

    // "12.300,5" -> "12300.5" (string numérico plano para el backend).
    function parse(raw) {
        var d = digitsAndDecimal(raw);

        return d.hasComma ? (d.intPart || '0') + '.' + (d.decPart || '0') : (d.intPart || '');
    }

    // "12300.50" (de la BD o de old()) -> "12.300,50" (formato de pantalla).
    // Solo se omite la coma cuando los decimales son enteramente cero
    // ("12300.00" -> "12.300"); "12300.50"/"12300.05" conservan sus dos dígitos.
    function fromNumeric(value) {
        if (value === null || value === undefined || value === '') return '';
        var parts = String(value).split('.');
        var grouped = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        var decimals = parts[1] || '';
        var isZero = decimals === '' || /^0+$/.test(decimals);

        return isZero ? grouped : grouped + ',' + decimals;
    }

    document.querySelectorAll('[data-money-input]').forEach(function (input) {
        input.value = fromNumeric(input.value);

        input.addEventListener('input', function () {
            input.value = format(input.value);
            input.dispatchEvent(new CustomEvent('money-input:change', { bubbles: true }));
        });

        var form = input.closest('form');
        if (form) {
            form.addEventListener('submit', function () {
                input.value = parse(input.value);
            });
        }
    });

    return { format: format, parse: parse, fromNumeric: fromNumeric };
})();

/*
|----------------------------------------------------------------------
| Confirmación de envío para formularios marcados con data-confirm.
|----------------------------------------------------------------------
| La confirmación NO usa window.confirm(): el modal propio de la app
| (#confirmModal en layouts/app.blade.php) intercepta el submit y muestra
| el mensaje con el diseño de Finlia. El texto se lee del atributo (ya
| escapado por Blade para el contexto HTML) y se inserta con textContent,
| nunca como HTML ni como código.
*/

/*
|----------------------------------------------------------------------
| PWA — Service Worker (Épica 10).
|----------------------------------------------------------------------
| Registra el SW mínimo que permite la instalación en pantalla de inicio.
| Solo se registra en HTTPS (o localhost) — el navegador lo ignora
| silenciosamente en http de red local, sin lanzar errores.
*/
if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () {
        navigator.serviceWorker.register('/sw.js').catch(function () {
            // Silencioso: la app funciona igual sin SW.
        });
    });
}

/*
|----------------------------------------------------------------------
| Selects inteligentes (Épica 10): recuerdan la última selección.
|----------------------------------------------------------------------
| Cualquier <select data-smart-select="CLAVE"> persiste en localStorage
| el último valor elegido y lo pre-selecciona la próxima vez que aparece
| en pantalla. La clave es libre (p.ej. "expense_account",
| "expense_category") y es por hogar (household_id en el meta) para que
| las preferencias de un hogar no contaminen a otro.
|
| El control real (el <select>) sigue siendo el que envía el formulario
| y mantiene `required` intacto; esto solo es comodidad, no lógica.
*/
(function () {
    // Prefijo de la clave incluye el household_id para aislamiento.
    var householdMeta = document.querySelector('meta[name="household-id"]');
    var householdId = householdMeta ? householdMeta.getAttribute('content') : 'default';
    var prefix = 'finlia-smart-' + householdId + '-';

    document.querySelectorAll('[data-smart-select]').forEach(function (select) {
        var key = prefix + select.getAttribute('data-smart-select');

        // Aplica la última selección guardada (solo si aún hay opción equivalente).
        var saved = null;
        try { saved = localStorage.getItem(key); } catch (e) {}
        if (saved && select.querySelector('option[value="' + saved + '"]')) {
            // Solo si el usuario no lo ha cambiado ya (old() de Blade).
            if (!select.value || select.value === '' || select.value === '0') {
                select.value = saved;
            }
        }

        // Persiste la nueva selección en cada cambio.
        select.addEventListener('change', function () {
            if (select.value) {
                try { localStorage.setItem(key, select.value); } catch (e) {}
            }
        });
    });
})();

/*
|----------------------------------------------------------------------
| Simulador de deuda (ADR-0023).
|----------------------------------------------------------------------
| Replica en el navegador la matemática de App\Services\DebtCalculator para
| que el usuario vea la cuota y la fecha de fin mientras escribe. La verdad
| sigue estando en el servidor: StoreDebtRequest valida la coherencia y
| DebtService recalcula al guardar. Esto es comodidad, no control.
*/
(function () {
    var form = document.querySelector('form [data-sim-amount]');
    if (!form) return;
    form = form.closest('form');

    var $ = function (sel) { return form.querySelector(sel); };

    var amount = $('[data-sim-amount]');
    var rate = $('[data-sim-rate]');
    var term = $('[data-sim-term]');
    var installment = $('[data-sim-installment]');
    var planned = $('[data-sim-planned]');
    var adjust = $('[data-sim-adjust]');
    var endOut = $('[data-sim-end]');
    var interestOut = $('[data-sim-interest]');
    var planHelp = $('[data-sim-plan-help]');
    var termHelp = $('[data-term-help]');
    var type = $('[data-debt-type]');

    if (!amount || !term || !installment) return;

    var limitsTag = document.querySelector('[data-debt-term-limits]');
    var limits = limitsTag ? JSON.parse(limitsTag.textContent) : {};

    var pesos = new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 });
    var mesAno = new Intl.DateTimeFormat('es-CO', { month: 'long', year: 'numeric' });

    function num(input) {
        if (!input) return null;
        var raw = input.hasAttribute('data-money-input')
            ? window.FinliaMoney.parse(input.value)
            : input.value;
        var v = parseFloat(raw);

        return isNaN(v) ? null : v;
    }

    // (1 + E.A.)^(1/12) − 1. Dividir entre 12 sería la convención nominal.
    function tasaMensual(anual) {
        if (!anual || anual <= 0) return 0;

        return Math.pow(1 + anual / 100, 1 / 12) - 1;
    }

    function cuota(monto, anual, cuotas) {
        if (!monto || monto <= 0 || !cuotas || cuotas < 1) return null;
        var i = tasaMensual(anual);
        var c = i <= 0 ? monto / cuotas : (monto * i) / (1 - Math.pow(1 + i, -cuotas));

        // Hacia arriba al céntimo, igual que en PHP: si no, faltaría un mes.
        return Math.ceil(c * 100) / 100;
    }

    function meses(saldo, anual, pago) {
        if (!saldo || saldo <= 0 || !pago || pago <= 0) return null;
        var i = tasaMensual(anual);
        if (pago <= saldo * i) return null;

        var n = 0, interes = 0;
        while (saldo > 0.005 && n < 600) {
            var delMes = Math.round(saldo * i * 100) / 100;
            interes += delMes;
            saldo = Math.round((saldo + delMes - pago) * 100) / 100;
            n++;
        }

        return n >= 600 ? null : { meses: n, interes: interes };
    }

    function pintar() {
        var monto = num(amount);
        var anual = num(rate);
        var cuotas = parseInt(term.value, 10) || null;
        var teorica = cuota(monto, anual, cuotas);

        // La cuota solo la escribe el simulador si el usuario no la ajustó.
        if (teorica !== null && (!adjust || !adjust.checked)) {
            installment.value = window.FinliaMoney.fromNumeric(teorica.toFixed(2));
        }

        var efectiva = num(installment);
        var resultado = meses(monto, anual, efectiva);

        if (resultado) {
            var fin = new Date();
            fin.setMonth(fin.getMonth() + resultado.meses);
            endOut.textContent = mesAno.format(fin);
            interestOut.textContent = anual > 0
                ? 'Intereses: ' + pesos.format(resultado.interes)
                : 'Sin intereses.';
        } else {
            endOut.textContent = '—';
            interestOut.textContent = efectiva && monto
                ? 'Con esa cuota la deuda no bajaría.'
                : '';
        }

        // Qué ganas pagando de más.
        var plan = num(planned);
        if (plan && efectiva && plan > efectiva && resultado) {
            var conPlan = meses(monto, anual, plan);
            if (conPlan) {
                var ahorro = resultado.interes - conPlan.interes;
                planHelp.textContent =
                    'Pagando ' + pesos.format(plan) + ' terminarías en ' + conPlan.meses +
                    ' meses en vez de ' + resultado.meses +
                    (ahorro > 1 ? ', ahorrando ' + pesos.format(ahorro) + ' en intereses.' : '.');
            }
        } else if (plan && efectiva && plan < efectiva) {
            planHelp.textContent = 'No puede ser menor que la cuota mensual.';
        } else {
            planHelp.textContent =
                'Déjalo vacío si vas a pagar la cuota. Si puedes abonar más, ponlo aquí y verás cuánto te ahorras.';
        }
    }

    function ajustarTope() {
        if (!type || !limits[type.value]) return;
        var max = limits[type.value];
        term.max = max;
        if (term.value && Number(term.value) > max) term.value = max;
        if (termHelp) termHelp.textContent = 'Máximo ' + max + ' para este tipo.';
    }

    function bloquearCuota() {
        installment.readOnly = !(adjust && adjust.checked);
    }

    [amount, rate, term, planned].forEach(function (el) {
        if (!el) return;
        el.addEventListener('input', pintar);
        el.addEventListener('money-input:change', pintar);
    });

    if (installment) {
        installment.addEventListener('input', pintar);
        installment.addEventListener('money-input:change', pintar);
    }

    if (type) {
        type.addEventListener('change', function () { ajustarTope(); pintar(); });
    }

    if (adjust) {
        adjust.addEventListener('change', function () { bloquearCuota(); pintar(); });
    }

    ajustarTope();
    bloquearCuota();
    pintar();
})();

/*
|----------------------------------------------------------------------
| Indicadores de carga (barra superior + botón ocupado).
|----------------------------------------------------------------------
| Finlia navega con recargas completas: entre el clic y la página nueva no
| hay ninguna señal, así que con el servidor lento el usuario cree que la
| app se pegó y vuelve a pulsar — y en un POST eso es una acción duplicada.
|
| Dos señales, ambas discretas:
|   · una barra fina arriba, para cualquier navegación (enlaces y envíos);
|   · un spinner dentro del botón pulsado, que además lo deja inerte.
|
| El botón NO se deshabilita: un `disabled` deja su name/value fuera del
| payload y rompería cualquier formulario que distinga qué botón lo envió.
| Se bloquea con `pointer-events: none` y con una marca en el formulario que
| descarta los envíos siguientes.
|
| Se expone en `window.Finlia.cargando` porque hay un envío que no pasa por
| el evento `submit`: el modal de confirmación usa
| `HTMLFormElement.prototype.submit()` justamente para no re-disparar su
| propio interceptor, y ahí hay que encender la barra a mano.
*/
window.Finlia = window.Finlia || {};
window.Finlia.cargando = (function () {
    // Retardo antes de mostrar nada: por debajo de esto la respuesta ya
    // llegó y un parpadeo de 80 ms es ruido, no información.
    var RETARDO_MS = 140;

    var barra = document.getElementById('finliaProgress');
    var temporizador = null;

    function iniciar() {
        if (!barra || temporizador || barra.classList.contains('is-activa')) return;

        temporizador = window.setTimeout(function () {
            temporizador = null;
            barra.classList.add('is-activa');
        }, RETARDO_MS);
    }

    function detener() {
        if (temporizador) {
            window.clearTimeout(temporizador);
            temporizador = null;
        }
        if (barra) barra.classList.remove('is-activa');
    }

    /**
     * Pone el botón en "enviando" sin cambiar su tamaño ni su payload.
     *
     * Cuando el botón tiene icono —que es lo normal en Finlia— el spinner
     * ocupa el sitio del icono y la etiqueta se queda: "⟳ Crear hogar" dice
     * qué está pasando, mientras que un botón vacío con una ruedita se lee
     * como si algo hubiera fallado. Solo cuando no hay icono que sustituir se
     * recurre a ocultar el contenido entero.
     */
    function ocuparBoton(boton) {
        if (!boton || boton.classList.contains('is-cargando')) return;

        var spinner = document.createElement('span');
        spinner.className = 'finlia-btn-spinner';
        spinner.setAttribute('aria-hidden', 'true');

        var icono = boton.querySelector('i');

        if (icono) {
            // `d-none` en vez de `visibility`: el spinner ocupa exactamente su
            // hueco, así que el botón no cambia de ancho igualmente.
            icono.classList.add('d-none');
            icono.dataset.finliaIconoOculto = '1';
            spinner.classList.add('finlia-btn-spinner-inline');
            boton.insertBefore(spinner, icono);
        } else {
            // `visibility: hidden` conserva el ancho (nada se mueve alrededor)
            // pero saca el texto del árbol de accesibilidad: sin el aria-label
            // de respaldo el botón se quedaría sin nombre justo mientras
            // espera. Los botones de solo icono ya traen el suyo y no se tocan.
            var etiqueta = (boton.textContent || '').trim();
            if (etiqueta && !boton.hasAttribute('aria-label')) {
                boton.setAttribute('aria-label', etiqueta);
                boton.dataset.finliaEtiquetaTemp = '1';
            }

            var envoltorio = document.createElement('span');
            envoltorio.className = 'invisible';
            while (boton.firstChild) {
                envoltorio.appendChild(boton.firstChild);
            }

            spinner.classList.add('finlia-btn-spinner-centrado');
            boton.appendChild(envoltorio);
            boton.appendChild(spinner);
        }

        boton.classList.add('is-cargando');
        boton.setAttribute('aria-busy', 'true');
    }

    /**
     * Marca el formulario como enviándose. Devuelve false si ya lo estaba,
     * que es la señal para descartar el envío repetido.
     */
    function ocuparFormulario(form, boton) {
        if (!form) return true;
        if (form.dataset.finliaEnviando === '1') return false;

        form.dataset.finliaEnviando = '1';
        ocuparBoton(boton || form.querySelector('button[type="submit"], button:not([type])'));
        iniciar();

        return true;
    }

    /* --- Envíos de formulario ------------------------------------------ */

    // Sin capture: corre después de los interceptores del modal de
    // confirmación y del formato de dinero. Si alguno canceló el envío, no
    // hay navegación que anunciar.
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!(form instanceof HTMLFormElement)) return;
        if (e.defaultPrevented) return;
        if (form.hasAttribute('data-sin-progreso')) return;

        if (!ocuparFormulario(form, e.submitter)) {
            e.preventDefault();
        }
    });

    /* --- Navegación por enlaces ---------------------------------------- */

    document.addEventListener('click', function (e) {
        // Clic con modificador o con otro botón: el navegador abre en otra
        // pestaña y esta página no se va a ninguna parte.
        if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;

        var enlace = e.target.closest && e.target.closest('a[href]');
        if (!enlace) return;
        if (enlace.hasAttribute('data-sin-progreso')) return;
        if (enlace.hasAttribute('download')) return;          // descarga: la página no cambia
        if (enlace.target && enlace.target !== '_self') return;
        if (enlace.hasAttribute('data-bs-toggle')) return;    // dropdown, modal, collapse…

        var href = enlace.getAttribute('href') || '';
        if (!href || href.charAt(0) === '#') return;
        if (/^(javascript|mailto|tel|sms):/i.test(href)) return;
        if (enlace.origin !== window.location.origin) return; // sitio externo

        // Ancla dentro de la misma página: no hay carga que esperar.
        if (enlace.pathname === window.location.pathname
            && enlace.search === window.location.search
            && enlace.hash) return;

        iniciar();
    });

    /* --- Reinicios ------------------------------------------------------ */

    // Volver con el botón atrás restaura la página desde la bfcache tal como
    // se dejó: con la barra a medias y el botón girando eternamente si no se
    // limpia aquí.
    window.addEventListener('pageshow', function () {
        detener();
        document.querySelectorAll('.is-cargando').forEach(liberarBoton);
        document.querySelectorAll('form[data-finlia-enviando]').forEach(function (form) {
            delete form.dataset.finliaEnviando;
        });
    });

    function liberarBoton(boton) {
        var spinner = boton.querySelector(':scope > .finlia-btn-spinner');
        if (spinner) spinner.remove();

        var icono = boton.querySelector('[data-finlia-icono-oculto]');
        if (icono) {
            icono.classList.remove('d-none');
            delete icono.dataset.finliaIconoOculto;
        }

        var envoltorio = boton.querySelector(':scope > span.invisible');
        if (envoltorio) {
            while (envoltorio.firstChild) {
                boton.insertBefore(envoltorio.firstChild, envoltorio);
            }
            envoltorio.remove();
        }

        if (boton.dataset.finliaEtiquetaTemp === '1') {
            boton.removeAttribute('aria-label');
            delete boton.dataset.finliaEtiquetaTemp;
        }

        boton.classList.remove('is-cargando');
        boton.removeAttribute('aria-busy');
    }

    return {
        iniciar: iniciar,
        detener: detener,
        ocuparBoton: ocuparBoton,
        ocuparFormulario: ocuparFormulario,
    };
})();
