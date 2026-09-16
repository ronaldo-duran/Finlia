{{--
    Guía de la pantalla actual (ADR-0045).

    Este partial no decide nada: la guía y si arranca sola vienen ya resueltas
    en `$finliaTour`, que comparte el middleware ShareActiveTour. Si la
    pantalla no tiene guía, la variable no existe y aquí no se pinta nada.

    El contenido de las guías vive en config/tours.php y el motor que las
    pinta en resources/js/tour.js.

    JSON_HEX_TAG por la misma razón que los gráficos del Panel: sin él, un
    "</script>" dentro de un texto cerraría este bloque; y el {{ }} de Blade
    escaparía las comillas a &quot;, que JSON.parse no sabe leer dentro de
    <script> porque el navegador no decodifica entidades ahí.
--}}
@php
    $tour = $finliaTour ?? null;
@endphp

@if ($tour && $tour['payload'])
    <script type="application/json" id="finlia-tour-data">{!! json_encode([
        'start' => $tour['start'],
        'guide' => $tour['payload'],
        'urls' => [
            'seen' => route('tours.store', $tour['payload']['key']),
            'preference' => route('tours.preference'),
        ],
    ], JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) !!}</script>
@endif
