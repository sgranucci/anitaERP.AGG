@extends('layouts.requisicion-aprobacion-publica')

@section('titulo_pagina', 'Aprobar orden de compra '.$ordencompra->numeroordencompra)

@section('content')
@include('configuracion.arbolaprobacion.partials.ordencompra_portal_resumen', ['modoPortal' => 'aprobar'])
@include('configuracion.arbolaprobacion.partials.panel_ia_contexto_arbol', [
    'ai_contexto_arbol' => $ai_contexto_arbol ?? null,
])

<div class="card card-success portal-card">
    <div class="card-header">
        <h2 class="card-title mb-0 h6">Confirmar aprobación</h2>
    </div>
    <form action="{{ route('aprobar_ordencompra_externo') }}" method="post" class="card-body">
        @csrf
        <input type="hidden" name="comprobante_id" value="{{ $ordencompra->id }}">
        <input type="hidden" name="aprobacion_id" value="{{ $movimiento->id }}">
        <input type="hidden" name="usuario_id" value="{{ $movimiento->destinatariousuario_id }}">
        <input type="hidden" name="hash_aprobacion" value="{{ $hash_aprobacion }}">
        <div class="form-group">
            <label for="observacion">Observaciones <span class="text-muted font-weight-normal">(opcional)</span></label>
            <textarea class="form-control" id="observacion" name="observacion" rows="4" maxlength="4000" placeholder="Comentarios internos sobre esta aprobación…">{{ old('observacion') }}</textarea>
        </div>
        <button type="submit" class="btn btn-success btn-block btn-lg">
            @if (!empty($es_circuito_legajo_gastronomia))
                Autorizar legajo (pasa a Cuentas a pagar)
            @else
                Aprobar orden de compra
            @endif
        </button>
    </form>
</div>
@endsection
