@php
    $titulo = $title ?? 'Finlia — Finanzas personales y familiares';
    $descripcion = $description ?? 'Finlia te dice cuánto dinero puedes gastar hoy sin comprometer el arriendo, las cuotas ni tus metas. Gastos, ingresos, deudas y ahorro de tu hogar, desde el celular.';
    $canonica = $canonical ?? url()->current();
    $imagen = $ogImage ?? asset('img/og-finlia.png');
    $indexable = $noindex ?? false;

    $datosEstructurados = $schema ?? [
        '@context' => 'https://schema.org',
        '@type' => 'WebSite',
        'name' => 'Finlia',
        'url' => route('home'),
    ];
@endphp
<!DOCTYPE html>
<html lang="es-CO" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $titulo }}</title>
    <meta name="description" content="{{ $descripcion }}">
    @isset($keywords)
        <meta name="keywords" content="{{ $keywords }}">
    @endisset
    <link rel="canonical" href="{{ $canonica }}">
    @if ($indexable)
        <meta name="robots" content="noindex, nofollow">
    @else
        <meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1">
    @endif
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Finlia">
    <meta property="og:locale" content="es_CO">
    <meta property="og:title" content="{{ $titulo }}">
    <meta property="og:description" content="{{ $descripcion }}">
    <meta property="og:url" content="{{ $canonica }}">
    <meta property="og:image" content="{{ $imagen }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="Finlia — finanzas personales y familiares">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $titulo }}">
    <meta name="twitter:description" content="{{ $descripcion }}">
    <meta name="twitter:image" content="{{ $imagen }}">
    <link rel="alternate" type="text/plain" href="{{ route('llms') }}" title="Resumen de Finlia para asistentes de IA">
    @include('layouts.partials.favicon')
    <meta name="theme-color" content="#eef3f8" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#0e1419" media="(prefers-color-scheme: dark)">
    @include('layouts.partials.theme-head')
    @vite(['resources/css/app.css', 'resources/css/marketing.css', 'resources/js/app.js'])
    <script type="application/ld+json">
        {!! json_encode($datosEstructurados, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}
    </script>
</head>
<body class="marketing d-flex flex-column min-vh-100">
    @include('marketing.partials.nav')

    <main id="contenido" class="flex-grow-1">
        @yield('content')
    </main>
    @include('marketing.partials.footer')
</body>
</html>
