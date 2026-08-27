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
