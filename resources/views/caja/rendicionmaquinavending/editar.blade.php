@extends("theme.$theme.layout")
@section('titulo')
    Editar rendici&oacute;n vending caja
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
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('caja.rendicionmaquinavending.partials.flash_mensajes')
        <div class="card card-danger">
            <div class="card-header d-flex align-items-center flex-wrap">
                <h3 class="card-title mb-0">Editar presentaci&oacute;n #{{ $data->id }}</h3>
                @if (! empty($nombreCaja))
                <span class="badge badge-light border ml-2 mb-0 py-1 px-2" style="font-size:0.85rem;font-weight:600;">
                    <i class="fa fa-inbox mr-1"></i>Caja: {{ $nombreCaja }}
                </span>
                @endif
                <div class="card-tools ml-auto">
                    @if (can('listar-rendicion-maquinavending-caja', false))
                    <a href="{{ route('imprimir_rendicion_maquinavending', ['id' => $data->id, 'inline' => 1]) }}"
                       class="btn btn-primary btn-sm" target="_blank" rel="noopener" title="Imprimir comprobante caja">
                        <i class="fa fa-print"></i> Imprimir
                    </a>
                    @endif
                    <a href="{{ route('rendicionmaquinavending') }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            <form action="{{ route('actualizar_rendicionmaquinavending', ['id' => $data->id]) }}" method="POST" id="form-rendicion-mv-caja" class="form-horizontal form--label-right" autocomplete="off">
                @csrf
                @method('PUT')
                <div class="card-body">
                    @include('caja.rendicionmaquinavending.form', [
                        'caja_id' => $data->caja_id,
                        'comprobante_ventas_url' => (
                            ($data->maquinavending_rendicion_id ?? 0) > 0
                            && can('ver-comprobante-maquinavending-rendicion-gastronomia', false)
                        ) ? route('maquinavending_rendicion_comprobante', ['id' => $data->maquinavending_rendicion_id, 'inline' => 1]) : null,
                    ])
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
