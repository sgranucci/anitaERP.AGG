@extends("theme.$theme.layout")
@section('titulo')
    Rendiciones estacionamiento
@endsection

@section('styles')
@include('caja.rendicionestacionamiento.partials.estilos_totales_turno')
@endsection

@section("scripts")
<script>
    window.RENDICION_GASTRONOMIA = {
        urlConsultaCierre: @json(route('api_rendicion_estacionamiento_consulta_cierre')),
        rendicionId: '',
    };
</script>
<script src="{{ asset('assets/pages/scripts/admin/crear.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/caja/estacionamiento/totales_turno_render.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/caja/estacionamiento/totales_turno_render.js')) }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/caja/rendicion_estacionamiento/form.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/caja/rendicion_estacionamiento/form.js')) }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/caja/rendicion_estacionamiento/consulta_cierre.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/caja/rendicion_estacionamiento/consulta_cierre.js')) }}" type="text/javascript"></script>
@endsection

@section('contenido')
@php
    $volverListadoUrl = route('rendicionestacionamiento', $filtrosQuery ?? []);
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('caja.rendicionestacionamiento.partials.flash_mensajes')
        <div class="card card-danger">
            <div class="card-header d-flex align-items-center flex-wrap">
                <h3 class="card-title mb-0">Registrar rendición de estacionamiento</h3>
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
                    <a href="{{ $volverListadoUrl }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            <form action="{{ route('guardar_rendicionestacionamiento', $filtrosQuery ?? []) }}" id="form-rendicion-estacionamiento" class="form-horizontal form--label-right" method="POST" autocomplete="off">
                @csrf
                <div class="card-body">
                    @include('caja.rendicionestacionamiento.form')
                </div>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-lg-3"></div>
                        <div class="col-lg-6">
                            <div id="bloque-verificacion-footer" class="alert alert-warning border mb-3 py-2">
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input" id="chk_verificacion_estacionamiento" disabled>
                                    <label class="custom-control-label font-weight-bold" for="chk_verificacion_estacionamiento">
                                        Verifiqué los datos del cierre de estacionamiento con lo entregado por el operador
                                    </label>
                                </div>
                                <small class="text-muted d-block mt-1" id="hint-verificacion-footer">
                                    Primero cargue el cierre (Consultar o número + Enter). Luego marque esta casilla para habilitar Guardar.
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
@include('caja.rendicionestacionamiento.modal_consulta_cierre')
@endsection
