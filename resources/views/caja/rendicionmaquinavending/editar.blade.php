@extends("theme.$theme.layout")
@section('titulo')
    @if (! empty($soloConsulta))
        Consultar rendici&oacute;n vending caja
    @else
        Editar rendici&oacute;n vending caja
    @endif
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
<script src="{{ asset('assets/pages/scripts/admin/editar.js') }}"></script>
<script src="{{ asset('assets/pages/scripts/caja/rendicion_maquinavending/form.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/caja/rendicion_maquinavending/form.js')) }}"></script>
@endsection

@section('contenido')
@php
    $soloConsulta = ! empty($soloConsulta);
    $soloLectura = $soloConsulta || empty($puedeActualizarRendicion);
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('caja.rendicionmaquinavending.partials.flash_mensajes')
        <div class="card card-danger">
            <div class="card-header d-flex align-items-center flex-wrap">
                <h3 class="card-title mb-0">
                    @if ($soloConsulta)
                        Consultar presentaci&oacute;n #{{ $data->id }}
                    @else
                        Editar presentaci&oacute;n #{{ $data->id }}
                    @endif
                </h3>
                @if (! empty($nombreCaja))
                <span class="badge badge-light border ml-2 mb-0 py-1 px-2" style="font-size:0.85rem;font-weight:600;">
                    <i class="fa fa-inbox mr-1"></i>Caja: {{ $nombreCaja }}
                </span>
                @endif
                <div class="card-tools ml-auto">
                    @if (\App\Support\Contable\CierreRendicionOrigenConsultaSupport::puedeVerPdfRendicionMaquinavending())
                    <a href="{{ route('imprimir_rendicion_maquinavending', ['id' => $data->id, 'inline' => 1]) }}"
                       class="btn btn-primary btn-sm" target="_blank" rel="noopener" title="Imprimir comprobante caja">
                        <i class="fa fa-print"></i> Imprimir
                    </a>
                    @endif
                    @if (! $soloConsulta)
                    <a href="{{ route('rendicionmaquinavending') }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                    @endif
                </div>
            </div>
            <form action="{{ route('actualizar_rendicionmaquinavending', ['id' => $data->id]) }}" method="POST" id="form-rendicion-mv-caja" class="form-horizontal form--label-right" autocomplete="off" @if($soloLectura) onsubmit="return false;" @endif>
                @csrf
                @method('PUT')
                <div class="card-body @if($soloLectura) pe-none @endif" @if($soloLectura) style="opacity:.92" @endif>
                    @include('caja.rendicionmaquinavending.form', [
                        'caja_id' => $data->caja_id,
                        'comprobante_ventas_url' => (
                            ($data->maquinavending_rendicion_id ?? 0) > 0
                            && \App\Support\Contable\CierreRendicionOrigenConsultaSupport::puedeVerPdfRendicionVentasMaquinavending()
                        ) ? route('maquinavending_rendicion_comprobante', ['id' => $data->maquinavending_rendicion_id, 'inline' => 1]) : null,
                    ])
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
