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
        rendicionId: @json($data->id),
    };
</script>
<script src="{{ asset('assets/pages/scripts/admin/editar.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/caja/estacionamiento/totales_turno_render.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/caja/estacionamiento/totales_turno_render.js')) }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/caja/rendicion_estacionamiento/form.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/caja/rendicion_estacionamiento/form.js')) }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/caja/rendicion_estacionamiento/consulta_cierre.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/caja/rendicion_estacionamiento/consulta_cierre.js')) }}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('caja.rendicionestacionamiento.partials.flash_mensajes')
        <div class="card card-danger">
            <div class="card-header d-flex align-items-center flex-wrap">
                <h3 class="card-title mb-0">Editar rendición #{{ $data->id }}</h3>
                @if (! empty($nombreCaja))
                <span class="badge badge-light border ml-2 mb-0 py-1 px-2" style="font-size:0.85rem;font-weight:600;">
                    <i class="fa fa-inbox mr-1"></i>Caja: {{ $nombreCaja }}
                </span>
                @endif
                <div class="card-tools ml-auto">
                    @if (\App\Support\Caja\RendicionEstacionamientoPdfPermiso::puedeVerPdfRendicion())
                    <a href="{{ route('imprimir_rendicion_estacionamiento', ['id' => $data->id, 'inline' => 1]) }}" class="btn btn-primary btn-sm" target="_blank" rel="noopener" title="Ver PDF rendición">
                        <i class="fa fa-print"></i> Imprimir
                    </a>
                    @endif
                    <a href="{{ route('rendicionestacionamiento') }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            <form action="{{ route('actualizar_rendicionestacionamiento', ['id' => $data->id]) }}" id="form-rendicion-estacionamiento" class="form-horizontal form--label-right" method="POST" autocomplete="off">
                @csrf @method('PUT')
                <div class="card-body">
                    @include('caja.rendicionestacionamiento.form')
                </div>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-lg-3"></div>
                        <div class="col-lg-6">
                            @include('includes.boton-form-editar')
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
