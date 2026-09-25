@php
    $title = 'Historial de versiones — Finlia';
    $description = 'Todo lo que Finlia ha entregado, versión a versión: nuevas funciones para gestionar tus finanzas personales y familiares, mejoras y correcciones, desde el primer día.';
    $schema = [
        '@context' => 'https://schema.org',
        '@type' => 'CollectionPage',
        'name' => 'Historial de versiones de Finlia',
        'description' => $description,
        'url' => route('changelog.show'),
        'inLanguage' => 'es-CO',
        'about' => [
            '@type' => 'SoftwareApplication',
            'name' => 'Finlia',
            'applicationCategory' => 'FinanceApplication',
            'softwareVersion' => config('finlia.version'),
        ],
    ];
@endphp

@extends('marketing.layout', ['title' => $title, 'description' => $description, 'schema' => $schema])

@section('content')
    <section class="seccion">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-12 col-lg-9">
                    <div class="text-center mb-5">
                        <p class="etiqueta-seccion">Cambios</p>
                        <h1 class="titulo-seccion">Historial de versiones</h1>
                        <p class="texto-seccion mx-auto" style="max-width: 56ch;">
                            Todo lo que Finlia ha entregado, con fecha y detalle.
                            La versión vigente es
                            <strong class="text-finlia">v{{ config('finlia.version') }}</strong>.
                        </p>
                    </div>

                    <article class="card p-4 p-md-5 changelog-content">
                        {!! $html !!}
                    </article>

                    <p class="text-center small text-muted mt-4 mb-0">
                        El archivo fuente está en
                        <a href="https://github.com/ronaldo-duran/Finlia/blob/main/CHANGELOG.md"
                           rel="noopener">
                            CHANGELOG.md
                        </a>
                        del repositorio.
                    </p>
                </div>
            </div>
        </div>
    </section>
@endsection
