{{--
    Tarjeta 1200×630 que se convierte en public/img/og-finlia.png.

    Es una página y no una imagen dibujada a mano para que se regenere con
    `npm run screenshots` cuando cambie el mensaje o la marca. No extiende el
    layout de marketing: aquí sobran nav, pie y metadatos, y hacen falta unas
    medidas exactas.
--}}
<!DOCTYPE html>
<html lang="es-CO" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="robots" content="noindex, nofollow">
    <title>Finlia — tarjeta para compartir</title>
    @include('layouts.partials.favicon')
    @vite(['resources/css/app.css', 'resources/css/marketing.css'])
</head>
<body class="og-body">
    <div class="og-tarjeta">
        <div class="og-texto">
            <div class="og-marca">
                <x-brandmark :size="52" />
                <span>Finlia</span>
            </div>

            <h1>¿Cuánto puedes gastar hoy sin quedar mal a fin de mes?</h1>

            <p>Finanzas personales y familiares. Gratis, en pesos y en español.</p>

            <div class="og-pie">finlia.online</div>
        </div>

        <div class="og-figura">
            <img src="{{ asset('img/capturas/panel.png') }}" alt="">
        </div>
    </div>
</body>
</html>
