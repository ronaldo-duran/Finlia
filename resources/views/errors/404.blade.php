@extends('layouts.error')

@section('code', '404')
@section('title', 'Esta página no existe')
@section('message', 'El enlace pudo cambiar, o colarse un error al escribirlo. Desde el panel llegas a todo lo demás.')

@section('actions')
    <a href="{{ route('dashboard') }}" class="btn btn-finlia">
        <i class="bi bi-house-door me-1"></i> Ir al panel
    </a>
@endsection
