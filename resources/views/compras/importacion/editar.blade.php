@extends("theme.$theme.layout")
@section('titulo')
    Editar importación {{ $data->codigo ?? '' }}
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/admin/crear.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/proveedor/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/compras/proveedor/consulta.js')) }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/configuracion/moneda/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/configuracion/moneda/consulta.js')) }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/ventas/incoterm/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/ventas/incoterm/consulta.js')) }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/configuracion/pais/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/configuracion/pais/consulta.js')) }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/importacion/form.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/compras/importacion/form.js')) }}" type="text/javascript"></script>
@endsection

@section('contenido')
@php
    $modoConsulta = request('vista') === 'consulta';
    $puedeEditar = can('actualizar-importacion', false);
@endphp
<div class="row">
    <div class="col-lg-12">
        <div class="card card-primary {{ $modoConsulta ? 'pe-none' : '' }}">
            <div class="card-header">
                <h3 class="card-title">Importación {{ $data->codigo }}</h3>
                <div class="card-tools">
                    @if ($modoConsulta)
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.close()">Cerrar solapa</button>
                    @else
                        <a href="{{ route('manual_importacion') }}" class="btn btn-outline-info btn-sm" target="_blank" rel="noopener" title="Manual del módulo">
                            <i class="fas fa-book-open"></i> Manual
                        </a>
                        <a href="{{ route('importacion', $filtrosQuery ?? []) }}" class="btn btn-outline-info btn-sm">
                            <i class="fa fa-reply-all"></i> Volver al listado
                        </a>
                    @endif
                </div>
            </div>
            <div class="card-body">
                @include('compras.importacion.form')
            </div>
            @if (! $modoConsulta && $puedeEditar)
                <div class="card-footer">
                    <button type="submit" form="form-importacion" class="btn botonsubmit btn-success">
                        <i class="fa fa-save"></i> Actualizar
                    </button>
                </div>
            @endif
        </div>
    </div>
</div>

@include('includes.compras.modalconsultaproveedor')
@include('includes.configuracion.modalconsultamoneda')
@include('includes.ventas.modalconsultaincoterm')
@include('includes.configuracion.modalconsultapais')
@include('includes.compras.modalconsulta_importacion_oc')
@include('includes.compras.modalconsulta_importacion_lote')
@include('includes.compras.modalconsulta_importacion_gasto_tipo')
@include('includes.compras.modalconsulta_importacion_cp')
@include('includes.compras.modalconsulta_importacion_com')
@endsection
