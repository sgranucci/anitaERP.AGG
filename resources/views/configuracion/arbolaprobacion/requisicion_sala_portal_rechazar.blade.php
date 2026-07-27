@extends('layouts.requisicion-aprobacion-publica')

@section('titulo_pagina', 'Rechazar requisición de sala '.$requisicion_sala->numerorequisicion)
@section('portal_nav_subtitulo', 'Rechazo requisición de sala')

@section('content')
@include('configuracion.arbolaprobacion.partials.requisicion_sala_portal_resumen', ['modoPortal' => 'rechazo'])
@include('configuracion.arbolaprobacion.partials.panel_ia_contexto_arbol', [
    'ai_contexto_arbol' => $ai_contexto_arbol ?? null,
])

<div class="card card-danger portal-card">
    <div class="card-header">
        <h2 class="card-title mb-0 h6">Confirmar rechazo</h2>
    </div>
    <form action="{{ route('rechazar') }}" method="post" class="card-body">
        @csrf
        @method('put')
        <input type="hidden" name="portal_publico" value="1">
        <input type="hidden" name="hash_rechazo" value="{{ $hash_rechazo }}">
        <input type="hidden" name="tipocomprobante" value="{{ $tipocomprobante }}">
        <input type="hidden" name="comprobante_id" value="{{ $comprobante_id }}">
        <input type="hidden" name="aprobacion_id" value="{{ $aprobacion_id }}">
        <input type="hidden" name="usuario_id" value="{{ $usuario_id }}">
        <div class="form-group">
            <label for="observacion">Motivo u observaciones <span class="text-danger">*</span></label>
            <textarea class="form-control" id="observacion" name="observacion" rows="5" required minlength="3" maxlength="4000" placeholder="Indique el motivo del rechazo…">{{ old('observacion') }}</textarea>
        </div>
        <button type="submit" class="btn btn-danger btn-block btn-lg">Rechazar requisición de sala</button>
    </form>
</div>
@endsection
