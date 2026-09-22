@extends('layouts.error')

@section('code', '503')
@section('title', 'Estamos actualizando Finlia')
@section('message', 'Es una actualización, no una caída: tus datos están a salvo. Suele tardar menos de un minuto.')
@section('actions')
    <button type="button" class="btn btn-finlia" onclick="window.location.reload()">
        <i class="bi bi-arrow-clockwise me-1"></i> Reintentar
    </button>
@endsection
