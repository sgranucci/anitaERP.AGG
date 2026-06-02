@extends("theme.$theme.layout")
@section('titulo')
    Rendiciones gastronomía
@endsection

@section('styles')
@include('caja.rendiciongastronomia.partials.estilos_totales_turno')
@endsection

@section("scripts")
<script>
    window.RENDICION_GASTRONOMIA = {
        urlConsultaCierre: @json(route('api_rendicion_gastronomia_consulta_cierre')),
        rendicionId: '',
    };
</script>
<script src="{{ asset('assets/pages/scripts/admin/crear.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/ventas/gastronomia/totales_turno_render.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/ventas/gastronomia/totales_turno_render.js')) }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/caja/rendicion_gastronomia/form.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/caja/rendicion_gastronomia/form.js')) }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/caja/rendicion_gastronomia/consulta_cierre.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/caja/rendicion_gastronomia/consulta_cierre.js')) }}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('caja.rendiciongastronomia.partials.flash_mensajes')
        <div class="card card-danger">
            <div class="card-header d-flex align-items-center flex-wrap">
                <h3 class="card-title mb-0">Registrar rendición de gastronomía</h3>
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
                    <a href="{{ route('rendiciongastronomia') }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            <form action="{{ route('guardar_rendiciongastronomia') }}" id="form-rendicion-gastronomia" class="form-horizontal form--label-right" method="POST" autocomplete="off">
                @csrf
                <div class="card-body">
                    @include('caja.rendiciongastronomia.form')
                </div>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-lg-3"></div>
                        <div class="col-lg-6">
                            <div id="bloque-verificacion-footer" class="alert alert-warning border mb-3 py-2">
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input" id="chk_verificacion_gastronomia" disabled>
                                    <label class="custom-control-label font-weight-bold" for="chk_verificacion_gastronomia">
                                        Verifiqué los datos del cierre de gastronomía con lo entregado por el operador
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
@include('caja.rendiciongastronomia.modal_consulta_cierre')
@endsection
