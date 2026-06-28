@extends("theme.$theme.layout")
@section('titulo')
    Presentar rendici&oacute;n vending
@endsection

@section('styles')
@include('caja.rendicionmaquinavending.partials.estilos_form')
@endsection

@section('scripts')
<script>
    window.RENDICION_MV_CAJA = {
        urlConsulta: @json(route('api_rendicion_maquinavending_consulta_rendicion')),
        urlDatos: @json(route('api_rendicion_maquinavending_datos_rendicion')),
    };
</script>
<script src="{{ asset('assets/pages/scripts/admin/crear.js') }}"></script>
<script src="{{ asset('assets/pages/scripts/caja/rendicion_maquinavending/consulta_rendicion.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/caja/rendicion_maquinavending/consulta_rendicion.js')) }}"></script>
<script src="{{ asset('assets/pages/scripts/caja/rendicion_maquinavending/form.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/caja/rendicion_maquinavending/form.js')) }}"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('caja.rendicionmaquinavending.partials.flash_mensajes')
        <div class="card card-danger">
            <div class="card-header d-flex align-items-center flex-wrap">
                <h3 class="card-title mb-0">Presentar rendici&oacute;n vending en caja</h3>
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
                    <a href="{{ route('rendicionmaquinavending') }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            <form action="{{ route('guardar_rendicionmaquinavending') }}" method="POST" id="form-rendicion-mv-caja" class="form-horizontal form--label-right" autocomplete="off">
                @csrf
                <div class="card-body">
                    @include('caja.rendicionmaquinavending.form')
                </div>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-lg-3"></div>
                        <div class="col-lg-6">
                            <div id="bloque-verificacion-footer" class="alert alert-warning border mb-3 py-2">
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input" id="chk_verificacion_mv" disabled>
                                    <label class="custom-control-label font-weight-bold" for="chk_verificacion_mv">
                                        Verifiqu&eacute; la rendici&oacute;n vending con lo entregado por el operador
                                    </label>
                                </div>
                                <small class="text-muted d-block mt-1" id="hint-verificacion-footer">
                                    Primero elija la rendici&oacute;n Ventas (Consultar). Luego marque esta casilla para habilitar Guardar.
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
@include('caja.rendicionmaquinavending.modal_consulta_rendicion')
@endsection
