@extends("theme.$theme.layout")
@section('titulo')
    Presentar rendición bingo
@endsection

@section('styles')
<style>
    .bingo-rend-panel thead th { background: #85C1E9; color: #17202A; }
    .bingo-rend-recaudacion { font-size: 1.25rem; font-weight: 700; color: #17202A; }
</style>
@endsection

@section("scripts")
<script>
    window.RENDICION_BINGO_CAJA = {
        urlConsultaCierre: @json(route('api_rendicion_bingo_consulta_cierre')),
        urlDatosTurno: @json(route('api_rendicion_bingo_datos_turno')),
    };
</script>
<script src="{{ asset('assets/pages/scripts/admin/crear.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/caja/rendicion_bingo/consulta_cierre.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/caja/rendicion_bingo/consulta_cierre.js')) }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/caja/rendicion_bingo/form.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/caja/rendicion_bingo/form.js')) }}" type="text/javascript"></script>
@if (session('url_comprobante_pdf'))
<script>
(function () {
    var url = @json(session('url_comprobante_pdf'));
    if (url) {
        window.open(url, '_blank', 'noopener');
    }
})();
</script>
@endif
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-danger">
            <div class="card-header d-flex align-items-center flex-wrap">
                <h3 class="card-title mb-0">Presentar rendición bingo en caja</h3>
                @if (($caja_id ?? 0) > 0)
                @php
                    $etiquetaCajaActiva = trim((string) ($nombreCaja ?? ''));
                    if ($etiquetaCajaActiva === '') {
                        $etiquetaCajaActiva = 'Caja #'.(int) $caja_id;
                    }
                @endphp
                <span class="badge badge-light border ml-2 mb-0 py-1 px-2" style="font-size:0.85rem;font-weight:600;">
                    <i class="fa fa-inbox mr-1"></i>{{ $etiquetaCajaActiva }}
                </span>
                @endif
                <div class="card-tools ml-auto">
                    <a href="{{ route('rendicionbingo') }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            <form action="{{ route('guardar_rendicionbingo') }}" id="form-rendicion-bingo-caja" class="form-horizontal form--label-right" method="POST" autocomplete="off">
                @csrf
                <div class="card-body">
                    @include('caja.rendicionbingo.form')
                </div>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-lg-3"></div>
                        <div class="col-lg-6">
                            <div id="bloque-verificacion-footer" class="alert alert-warning border mb-3 py-2">
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input" id="chk_verificacion_bingo" disabled>
                                    <label class="custom-control-label font-weight-bold" for="chk_verificacion_bingo">
                                        Verifiqué la rendición bingo con lo entregado por el operador
                                    </label>
                                </div>
                                <small class="text-muted d-block mt-1" id="hint-verificacion-footer">
                                    Primero elija el cierre de turno (Consultar). Luego marque esta casilla para habilitar Guardar.
                                </small>
                            </div>
                            @include('includes.boton-form-crear')
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@include('caja.rendicionbingo.modal_consulta_cierre')
@endsection
