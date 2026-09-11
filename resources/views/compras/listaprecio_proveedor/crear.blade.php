@extends("theme.$theme.layout")
@section('titulo')
Nueva lista de precios proveedor
@endsection

@section('styles')
<link rel="stylesheet" href="{{ asset('assets/css/compras/listaprecio-proveedor-ui.css') }}?v={{ @filemtime(public_path('assets/css/compras/listaprecio-proveedor-ui.css')) ?: time() }}">
@endsection

@section("scripts")
<script src="{{ asset('assets/pages/scripts/admin/crear.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/articulo/consulta.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/proveedor/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/compras/proveedor/consulta.js')) ?: time() }}" type="text/javascript"></script>
<script>
    window.lpImportPreviewUrl = @json(route('importar_preview_listaprecio_proveedor'));
    window.lpImportExcelUrl = null;
</script>
<script src="{{ asset('assets/pages/scripts/compras/listaprecio_proveedor/crear.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/compras/listaprecio_proveedor/crear.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/listaprecio_proveedor/importar.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/compras/listaprecio_proveedor/importar.js')) ?: time() }}" type="text/javascript"></script>
@endsection

@section('contenido')
@php
    $volverListadoUrl = route('consultar_listaprecio_proveedor', $filtrosQuery ?? []);
@endphp
<div class="row lp-form" id="crear">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">Nueva lista de precios</h3>
                <div class="card-tools">
                    <a href="{{ $volverListadoUrl }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            <form action="{{ route('guardar_listaprecio_proveedor', $filtrosQuery ?? []) }}" id="form-general" class="form-horizontal form--label-right" method="POST" enctype="multipart/form-data" autocomplete="off">
                @csrf
                <div class="card-body">
                    @include('includes.tabs-activas-estilos')
                    <div class="tabs-activas">
                        <ul class="nav nav-tabs" id="tabs-listaprecio-proveedor" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link active" data-toggle="tab" href="#tab-datos" role="tab">
                                    <i class="fa fa-info-circle"></i> Datos principales
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-toggle="tab" href="#tab-precios" role="tab">
                                    <i class="fa fa-list"></i> Precios
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-toggle="tab" href="#tab-archivos" role="tab">
                                    <i class="fa fa-paperclip"></i> Archivos
                                </a>
                            </li>
                        </ul>
                    </div>
                    <div class="tab-content pt-3">
                        <div class="tab-pane fade show active" id="tab-datos" role="tabpanel">
                            @include('compras.listaprecio_proveedor.form')
                        </div>
                        <div class="tab-pane fade" id="tab-precios" role="tabpanel">
                            @include('compras.listaprecio_proveedor.partials.solapa_precios')
                        </div>
                        <div class="tab-pane fade" id="tab-archivos" role="tabpanel">
                            @include('compras.listaprecio_proveedor.form_archivos')
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-lg-3"></div>
                        <div class="col-lg-6">
                            @include('includes.boton-form-crear')
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@include('includes.stock.modalconsultaarticulo')
@include('includes.compras.modalconsultaproveedor')
@endsection
