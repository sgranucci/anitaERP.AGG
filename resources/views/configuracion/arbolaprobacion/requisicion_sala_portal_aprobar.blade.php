@extends('layouts.requisicion-aprobacion-publica')

@section('titulo_pagina', 'Aprobar requisición de sala '.$requisicion_sala->numerorequisicion)
@section('portal_nav_subtitulo', 'Aprobación requisición de sala')

@section('content')
@include('configuracion.arbolaprobacion.partials.requisicion_sala_portal_resumen', ['modoPortal' => 'aprobar'])
@include('configuracion.arbolaprobacion.partials.requisicion_sala_alerta_transferencia_saldos')

<div class="card card-success portal-card">
    <div class="card-header">
        <h2 class="card-title mb-0 h6">Confirmar aprobación</h2>
    </div>
    <form action="{{ route('aprobar_requisicion_sala_externo') }}" method="post" class="card-body" id="form-aprobacion-requisicion-sala">
        @csrf
        <input type="hidden" name="comprobante_id" value="{{ $requisicion_sala->id }}">
        <input type="hidden" name="aprobacion_id" value="{{ $movimiento->id }}">
        <input type="hidden" name="usuario_id" value="{{ $movimiento->destinatariousuario_id }}">
        <input type="hidden" name="hash_aprobacion" value="{{ $hash_aprobacion }}">
        <div class="form-group">
            <label for="observacion">Observaciones <span class="text-muted font-weight-normal">(opcional)</span></label>
            <textarea class="form-control" id="observacion" name="observacion" rows="4" maxlength="4000" placeholder="Comentarios internos sobre esta aprobación…">{{ old('observacion') }}</textarea>
        </div>
        <button type="submit" class="btn btn-success btn-block btn-lg" id="btn-aprobar-requisicion-sala">Aprobar requisición de sala</button>
    </form>
</div>
@include('sala.requisicion_sala.partials.banner_grabando_styles')
@endsection

@push('scripts')
<script src="{{ asset('assets/pages/scripts/sala/requisicion_sala/grabando.js') }}"></script>
<script>
(function () {
    var form = document.getElementById('form-aprobacion-requisicion-sala');
    if (!form) {
        return;
    }
    form.addEventListener('submit', function () {
        var btn = document.getElementById('btn-aprobar-requisicion-sala');
        if (window.RequisicionSalaGrabando && typeof window.RequisicionSalaGrabando.mostrar === 'function') {
            window.RequisicionSalaGrabando.mostrar({
                titulo: 'Procesando aprobación…',
                subtitulo: 'Puede tardar unos minutos si debe registrar la transferencia de mercadería al laboratorio.<br>Por favor espere; no cierre esta ventana.'
            });
        }
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Procesando…';
        }
    });
})();
</script>
@endpush
