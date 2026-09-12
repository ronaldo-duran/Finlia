@extends('layouts.error')

{{--
    El mensaje de fábrica ("Page Expired") no le dice nada a nadie y aparece
    en un momento incómodo: el formulario que acabas de llenar. Aquí se
    explica qué pasó y qué hacer.
--}}
@section('code', '419')
@section('title', 'Tu sesión caducó')
@section('message', 'Estuvo quieta demasiado tiempo y, por seguridad, se cerró sola. Vuelve a entrar y sigue donde ibas.')

@section('actions')
    <a href="{{ route('login') }}" class="btn btn-finlia">
        <i class="bi bi-box-arrow-in-right me-1"></i> Entrar de nuevo
    </a>
@endsection
