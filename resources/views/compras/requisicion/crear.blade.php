@extends("theme.$theme.layout")
@section('titulo')
Requisiciones
@endsection

@section('styles')
<link rel="stylesheet" href="{{ asset('assets/css/compras/ordencompra-ui.css') }}?v={{ @filemtime(public_path('assets/css/compras/ordencompra-ui.css')) ?: time() }}">
<link rel="stylesheet" href="{{ asset('assets/css/compras/requisicion-ui.css') }}?v={{ @filemtime(public_path('assets/css/compras/requisicion-ui.css')) ?: time() }}">
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/crear.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/stock/articulo/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/presupuesto/partidagasto/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/presupuesto/capex/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/compras/proveedor/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/compras/articulo_proveedor/operativo.js")}}" type="text/javascript"></script>
<script>
window.requisicionLineasConfig = window.requisicionLineasConfig || {};
window.requisicionLineasConfig.urlPrecioUltimaCompra = @json(route('requisicion_precio_ultima_compra_articulo'));
window.requisicionLineasConfig.urlCalcularTotales = @json(route('requisicion_calcular_totales'));
window.requisicionEmpresaRecordar = { usuarioId: @json(auth()->id()) };
window.requisicionModoProvisorio = @json(!empty($modo_provisorio));
window.requisicionPideCcArbolAlGrabar = @json(empty($modo_provisorio));
window.requisicionUsaCcOrigenArbol = @json(\App\Support\Compras\RequisicionCentrocostoArbolOrigenSupport::usuarioPuedeCargar());
window.msColoresOpciones = @json(($color_query ?? collect())->map(fn ($c) => ['id' => (int) $c->id, 'nombre' => $c->nombre])->values());
window.msTallesOpciones = @json(($talle_query ?? collect())->map(fn ($t) => ['id' => (int) $t->id, 'nombre' => $t->nombre])->values());
</script>
<script src="{{ asset('assets/pages/scripts/compras/requisicion/lineas.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/compras/requisicion/lineas.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/compras/form-color-talle.js")}}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/requisicion/centrocosto-arbol-modal.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/compras/requisicion/centrocosto-arbol-modal.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/contable/centrocosto/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/contable/centrocosto/consulta.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/requisicion/centrocosto-arbol-grabacion.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/compras/requisicion/centrocosto-arbol-grabacion.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/requisicion/crear.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/compras/requisicion/crear.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/compras/requisicion/consulta-listasprecio.js")}}" type="text/javascript"></script>
@if(!empty($modo_provisorio))
<script src="{{ asset('assets/pages/scripts/compras/requisicion/confirmar.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/compras/requisicion/confirmar.js')) ?: time() }}" type="text/javascript"></script>
@endif
@endsection

@section('contenido')
@php
    $volverListadoUrl = route('consultar_requisicion', $filtrosQuery ?? []);
@endphp
@include('compras.requisicion.partials.modal_centrocosto_retome_arbol')
@if(!empty($modo_provisorio))
@include('compras.requisicion.partials.modal_confirmar_envio_arbol')
@endif
<div class="row oc-ui rq-ui" id="crear">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">Nueva requisición</h3>
                @include('compras.requisicion.partials.toolbar_acciones')
            </div>

            @include('compras.requisicion.partials.identidad')

            <form action="{{ route('guardar_requisicion', $filtrosQuery ?? []) }}" id="form-general" class="form-horizontal form--label-right" method="POST" enctype="multipart/form-data" autocomplete="off">
                @csrf
                @include('compras.requisicion.partials.tabs_header')
                <div class="card-body">
                    @if(!empty($modo_provisorio))
                    <div class="alert alert-info mb-3" role="alert">
                        <strong>Modo provisorio:</strong> la requisición se guardará sin enviar al árbol ni a Anita. Podrá revisarla y confirmarla después.
                    </div>
                    @else
                    @include('compras.requisicion.partials.aviso_arbol_grabacion')
                    @endif
                    @include('compras.requisicion.form')
                    <div class="form4" id="requisicion-solapa-archivos-adjuntos" style="display:none;">
                        @include('compras.requisicion.partials.solapa_agregar_archivos', ['data' => $data ?? null])
                    </div>
                </div>
                <div class="card-footer oc-form-footer">
                    <button type="button" id="botonform0" class="btn btn-success">
                        <i class="fa fa-save"></i>
                        @if(!empty($modo_provisorio))
                            Guardar provisorio
                        @else
                            Guardar
                        @endif
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
