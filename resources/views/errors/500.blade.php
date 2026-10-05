@extends('errors.layout')
@section('code', '500')
@section('title', 'No pudimos completar la operación')
@section('message')
    Vuelve al inicio y revisa si se guardó antes de intentarlo de nuevo.
    Si el problema continúa, comunica este código al administrador: {{ $incident }}.
@endsection
