@extends('layouts.requisicion-aprobacion-publica')

@section('titulo_pagina', 'Rechazar '.$etiqueta_tipo.' '.($numero_comprobante ?? $comprobante_id ?? ''))

@section('content')
<div class="card portal-card mb-3">
    <div class="card-header bg-danger">
        <h1 class="card-title mb-0 h5 text-white">{{ $etiqueta_tipo }} {{ $numero_comprobante ?? ('#'.$documento->id) }}</h1>
        <small class="text-white-50 d-block pl-3 mt-1">Nivel de aprobación: {{ $movimiento->nivel ?? '—' }}</small>
    </div>
    <div class="card-body">
        <dl class="row kv mb-0">
            <dt class="col-sm-4">Tipo</dt>
            <dd class="col-sm-8">{{ $etiqueta_tipo }} ({{ $tipocomprobante }})</dd>
            <dt class="col-sm-4">Monto</dt>
            <dd class="col-sm-8">
                @php
                    $monedaPortal = trim((string) ($moneda_abrev_items ?? optional($documento->monedas ?? null)->abreviatura ?? ''));
                    $montoPortal = number_format((float) ($monto_items ?? 0), 2, ',', '.');
                @endphp
                {{ $monedaPortal !== '' ? $monedaPortal.' ' : '' }}{{ $montoPortal }}
            </dd>
        </dl>
    </div>
</div>

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
        <button type="submit" class="btn btn-danger btn-block btn-lg">Rechazar {{ strtolower($etiqueta_tipo) }}</button>
    </form>
</div>
@endsection
