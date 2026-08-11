{{--
    Anti-FOUC + bootstrap del tema (claro/oscuro).
    Se incluye en <head> ANTES de @vite: fija data-bs-theme desde
    localStorage (o prefers-color-scheme) antes del primer paint,
    evitando el flash claro→oscuro al recargar.
--}}
<script>
    (function () {
        var t = localStorage.getItem('finlia-theme');
        if (t !== 'dark' && t !== 'light') {
            t = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        }
        document.documentElement.setAttribute('data-bs-theme', t);
    })();
</script>
<meta name="theme-color" media="(prefers-color-scheme: light)" content="#eef3f8">
<meta name="theme-color" media="(prefers-color-scheme: dark)" content="#0e1419">
