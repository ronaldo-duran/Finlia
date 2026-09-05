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

import 'bootstrap';

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
| Evita interpolar input del usuario dentro de JS inline (onsubmit="..."),
| lo que sería un vector de XSS en contexto de string JS. El texto del
| confirm se lee del atributo (ya escapado por Blade para el contexto
| HTML) y se pasa a window.confirm() como dato, nunca como código.
*/
document.addEventListener('submit', function (e) {
    var form = e.target.closest && e.target.closest('[data-confirm]');
    if (form && !window.confirm(form.getAttribute('data-confirm'))) {
        e.preventDefault();
    }
});

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
