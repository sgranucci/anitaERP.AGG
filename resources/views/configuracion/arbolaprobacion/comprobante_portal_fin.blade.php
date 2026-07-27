@extends('layouts.requisicion-aprobacion-publica')

@section('titulo_pagina', ($ok ?? false) ? 'Aprobación registrada' : 'No se pudo completar')

@section('content')
<div class="card portal-card {{ ($ok ?? false) ? 'card-success' : 'card-warning' }}">
    <div class="card-body">
        <h1 class="h5 mb-3">{{ ($ok ?? false) ? 'Listo' : 'Atención' }}</h1>
        <p class="mb-0">{{ $mensaje ?? '' }}</p>
    </div>
</div>
@endsection
