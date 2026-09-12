@extends('layouts.error')

{{--
    Modo mantenimiento. Se pre-renderiza al desplegar con
    `artisan down --render="errors::503"`, así que se sirve SIN arrancar la
    aplicación: nada de lo que hay aquí puede depender de sesión, rutas ni
    base de datos. Por eso el botón recarga con JavaScript en vez de
    enlazar a ninguna parte — durante el despliegue todo el sitio responde
    con esta misma página.
--}}
@section('code', '503')
@section('title', 'Estamos actualizando Finlia')
@section('message', 'Es una actualización, no una caída: tus datos están a salvo. Suele tardar menos de un minuto.')

@section('actions')
    <button type="button" class="btn btn-finlia" onclick="window.location.reload()">
        <i class="bi bi-arrow-clockwise me-1"></i> Reintentar
    </button>
@endsection
