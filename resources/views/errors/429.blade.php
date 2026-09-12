@extends('layouts.error')

{{--
    Se ve al agotar el rate limit de login, registro o exportación de datos.
    No lleva botón: pulsar otra vez es justo lo que no hay que hacer.
--}}
@section('code', '429')
@section('title', 'Demasiados intentos')
@section('message', 'Por seguridad hay que esperar un momento antes de volver a intentarlo. Con un minuto suele bastar.')
