@extends('layouts.error')

@section('code', '403')
@section('title', 'Esto no es tuyo')
@section('message', 'No tienes acceso a esta información: puede pertenecer a otro hogar, o tu rol en el hogar no permite esta acción.')

@section('actions')
    <a href="{{ route('dashboard') }}" class="btn btn-finlia">
        <i class="bi bi-house-door me-1"></i> Ir al panel
    </a>
@endsection
