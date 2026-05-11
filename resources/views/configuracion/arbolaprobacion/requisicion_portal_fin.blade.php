@extends('layouts.requisicion-aprobacion-publica')

@section('titulo_pagina', $ok ? 'Operación registrada' : 'No se pudo completar')

@section('content')
<div class="card portal-card shadow">
    <div class="card-body text-center py-5">
        @if($ok)
            <div class="mb-3 text-success" style="font-size: 3rem;"><i class="fa fa-check-circle"></i></div>
            <h1 class="h4 mb-3">Listo</h1>
        @else
            <div class="mb-3 text-warning" style="font-size: 3rem;"><i class="fa fa-exclamation-triangle"></i></div>
            <h1 class="h4 mb-3">Atención</h1>
        @endif
        <p class="lead mb-0 text-break">{{ $mensaje }}</p>
    </div>
</div>
@endsection
