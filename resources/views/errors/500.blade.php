@extends('layouts.error')

@section('code', '500')
@section('title', 'Algo se rompió de nuestro lado')
@section('message', 'No es culpa tuya y tus datos están a salvo. El fallo quedó registrado; vuelve a intentarlo en un momento.')

@section('actions')
    <button type="button" class="btn btn-finlia" onclick="window.location.reload()">
        <i class="bi bi-arrow-clockwise me-1"></i> Reintentar
    </button>
@endsection
