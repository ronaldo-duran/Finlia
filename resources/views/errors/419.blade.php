@extends('layouts.error')

@section('code', '419')
@section('title', 'Tu sesión caducó')
@section('message', 'Estuvo quieta demasiado tiempo y, por seguridad, se cerró sola. Vuelve a entrar y sigue donde ibas.')
@section('actions')
    <a href="{{ route('login') }}" class="btn btn-finlia">
        <i class="bi bi-box-arrow-in-right me-1"></i> Entrar de nuevo
    </a>
@endsection
