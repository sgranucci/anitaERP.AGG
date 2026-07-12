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
@php
    $soloConsulta = ! empty($soloConsulta);
    $soloLectura = $soloConsulta || empty($puedeActualizarRendicion);
    $volverListadoUrl = route('rendicionestacionamiento', $filtrosQuery ?? []);
    $paramsActualizar = ['id' => $data->id] + ($filtrosQuery ?? []);
    if ($soloConsulta) {
        $paramsActualizar = [
            'id' => $data->id,
            'origen' => 'modal_consulta',
            'vista' => 'consulta',
        ];
    }
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('caja.rendicionestacionamiento.partials.flash_mensajes')
        <div class="card card-danger">
            <div class="card-header d-flex align-items-center flex-wrap">
                <h3 class="card-title mb-0">
                    @if ($soloConsulta)
                        Consultar rendición #{{ $data->id }}
                    @else
                        Editar rendición #{{ $data->id }}
                    @endif
                </h3>
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
                    @if (empty($ocultarVolver))
                    <a href="{{ $volverListadoUrl }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                    @endif
                </div>
            </div>
            <form action="{{ route('actualizar_rendicionestacionamiento', $paramsActualizar) }}" id="form-rendicion-estacionamiento" class="form-horizontal form--label-right" method="POST" autocomplete="off" @if($soloLectura) onsubmit="return false;" @endif>
                @csrf @method('PUT')
                @if ($soloConsulta)
                    <input type="hidden" name="origen" value="modal_consulta">
                    <input type="hidden" name="vista" value="consulta">
                @endif
                <div class="card-body @if($soloLectura) pe-none @endif" @if($soloLectura) style="opacity:.92" @endif>
                    @include('caja.rendicionestacionamiento.form')
                </div>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-lg-3"></div>
                        <div class="col-lg-6 text-center">
                            @if (! $soloLectura)
                                @include('includes.boton-form-editar')
                            @elseif ($soloConsulta)
                                <button type="button" class="btn btn-secondary" onclick="window.close()">Cerrar solapa</button>
                            @endif
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
