@extends('layouts.requisicion-aprobacion-publica')

@section('titulo_pagina', 'Aprobar '.$etiqueta_tipo.' '.($numero_comprobante ?? $comprobante_id ?? ''))

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
            <dd class="col-sm-8">{{ number_format((float) ($monto_items ?? 0), 2, ',', '.') }}</dd>
            @if (!empty($estado_tras_aprobar))
                <dt class="col-sm-4">Tras aprobar este paso</dt>
                <dd class="col-sm-8"><strong>{{ $estado_tras_aprobar }}</strong></dd>
            @endif
        </dl>
    </div>
</div>

@include('configuracion.arbolaprobacion.partials.panel_ia_contexto_arbol', [
    'ai_contexto_arbol' => $ai_contexto_arbol ?? null,
])

<div class="card card-success portal-card">
    <div class="card-header">
        <h2 class="card-title mb-0 h6">Confirmar aprobación</h2>
    </div>
    <form action="{{ route('aprobar_comprobante_externo') }}" method="post" class="card-body">
        @csrf
        <input type="hidden" name="tipocomprobante" value="{{ $tipocomprobante }}">
        <input type="hidden" name="comprobante_id" value="{{ $documento->id }}">
        <input type="hidden" name="aprobacion_id" value="{{ $movimiento->id }}">
        <input type="hidden" name="usuario_id" value="{{ $movimiento->destinatariousuario_id }}">
        <input type="hidden" name="hash_aprobacion" value="{{ $hash_aprobacion }}">
        <div class="form-group">
            <label for="observacion">Observaciones <span class="text-muted font-weight-normal">(opcional)</span></label>
            <textarea class="form-control" id="observacion" name="observacion" rows="4" maxlength="4000" placeholder="Comentarios internos sobre esta aprobación…">{{ old('observacion') }}</textarea>
        </div>
        <button type="submit" class="btn btn-success btn-block btn-lg">Aprobar {{ strtolower($etiqueta_tipo) }}</button>
    </form>
</div>
@endsection
