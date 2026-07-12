@extends("theme.$theme.layout")
@section('titulo')
Requisiciones
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/crear.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/stock/articulo/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/presupuesto/partidagasto/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/presupuesto/capex/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/compras/proveedor/consulta.js")}}" type="text/javascript"></script>
<script>
window.requisicionLineasConfig = window.requisicionLineasConfig || {};
window.requisicionLineasConfig.urlPrecioUltimaCompra = @json(route('requisicion_precio_ultima_compra_articulo'));
window.requisicionLineasConfig.urlCalcularTotales = @json(route('requisicion_calcular_totales'));
window.requisicionEmpresaRecordar = { usuarioId: @json(auth()->id()) };
window.requisicionModoProvisorio = @json(!empty($modo_provisorio));
</script>
<script src="{{asset("assets/pages/scripts/compras/requisicion/lineas.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/compras/requisicion/crear.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/compras/requisicion/consulta-listasprecio.js")}}" type="text/javascript"></script>
@if(!empty($modo_provisorio))
<script src="{{ asset('assets/pages/scripts/compras/requisicion/confirmar.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/compras/requisicion/confirmar.js')) ?: time() }}" type="text/javascript"></script>
@endif
@endsection

@section('contenido')
@php
    $volverListadoUrl = route('consultar_requisicion', $filtrosQuery ?? []);
@endphp
<div class="row" id="crear">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-danger">
            <div class="card-header">
                <h3 class="card-title">Nueva requisición</h3>
                <div class="card-tools">
                    <a href="{{ $volverListadoUrl }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            <form action="{{ route('guardar_requisicion', $filtrosQuery ?? []) }}" id="form-general" class="form-horizontal form--label-right" method="POST" enctype="multipart/form-data" autocomplete="off">
                @csrf
                <div align="center" style="margin: 5px;">
                    <button type="button" id="botonform1" class="btn btn-primary btn-sm">
                        <i class="fa fa-user"></i> Datos principales
                    </button>
                    <button type="button" id="botonform4" class="btn btn-info btn-sm">
                        <span class="fa fa-paperclip"></span> Archivos asociados
                    </button>
                </div>
                <div class="card-body">
                    @if(!empty($modo_provisorio))
                    <div class="alert alert-info mb-3" role="alert">
                        <strong>Modo provisorio:</strong> la requisición se guardará sin enviar al árbol ni a Anita. Podrá revisarla y confirmarla después.
                    </div>
                    @else
                    <div id="requisicion-aviso-arbol-grabacion" class="alert alert-secondary mb-3 d-none" role="alert">
                        <span id="requisicion-aviso-arbol-spinner" class="fa fa-spinner fa-spin mr-1" style="display:none;" aria-hidden="true"></span>
                        <strong>Aviso:</strong> <span class="texto"></span>
                    </div>
                    @endif
                    @include('compras.requisicion.form')
                    <div class="form4" id="requisicion-solapa-archivos-adjuntos" style="display:none;">
                        @include('compras.requisicion.partials.solapa_agregar_archivos', ['data' => $data ?? null])
                    </div>
                </div>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-lg-3"></div>
                        <div class="col-lg-6">
                            <button type="button" id="botonform0" class="btn btn-success">
                                <i class="fa fa-save"></i>
                                @if(!empty($modo_provisorio))
                                    Guardar provisorio
                                @else
                                    Guardar
                                @endif
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
