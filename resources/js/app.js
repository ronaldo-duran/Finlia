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
