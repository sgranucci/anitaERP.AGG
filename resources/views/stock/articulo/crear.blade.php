@extends("theme.$theme.layout")
@section('titulo')
    Artículos
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/crear.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/stock/articulo/contable.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/stock/articulo/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/contable/cuentacontable/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset('assets/pages/scripts/stock/articulo/crear.js')}}?v=20260607" type="text/javascript"></script>
@if (can('editar-compras-articulos', false) || can('actualizar-compras-articulos', false))
<script src="{{ asset('assets/pages/scripts/compras/proveedor/consulta.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/articulo/proveedores.js') }}?v=20260607c" type="text/javascript"></script>
@endif
@if (can('listar-formula-articulo', false) || can('listar-articulos', false))
<script>
window.consultaFormulaArticuloConfig = {
    urlResolverBase: @json(url('stock/formula-articulo/resolver-por-articulo')),
    urlFormulaBase: @json(url('stock/formula-articulo')),
    puedeEditar: @json(can('editar-formula-articulo', false))
};
</script>
<script src="{{ asset('assets/pages/scripts/stock/articulo/formula-modal.js') }}" type="text/javascript"></script>
@endif
@endsection

@section('contenido')
@php
    $volverListadoUrl = route('articulo', $filtrosQuery ?? []);
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-danger">
            <div class="card-header">
                <h3 class="card-title">Crear Artículo</h3>
                <div class="card-tools">
                    @if (isset($urlOrigen))
                        <a href="javascript:history.back()" class="btn btn-outline-info btn-sm">
                            <i class="fa fa-fw fa-reply-all"></i> Volver atrás
                        </a>
                    @else
                        <a href="{{ $volverListadoUrl }}" class="btn btn-outline-info btn-sm">
                            <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                        </a>
                    @endif
                </div>
            </div>
            <form action="{{ route('guardar_articulo', $filtrosQuery ?? []) }}" id="form-general" enctype="multipart/form-data" class="form-horizontal form--label-right" method="POST" autocomplete="off">
                @csrf
                <div align="center" style="margin: 5px;">
                    <button type="button" id="botonform1" class="btn btn-primary btn-sm">
                        <i class="fa fa-user"></i> Datos principales
                    </button>
                    @if (can('editar-formula-articulos', false) || can('actualizar-formula-articulos', false))
                        <button type="button" id="botonform2" class="btn btn-info btn-sm">
                            <span class="fa fa-copy"></span> Fórmulas
                        </button>
                    @endif  
                    @if (can('editar-compras-articulos', false) || can('actualizar-compras-articulos', false))
                        <button type="button" id="botonform3" class="btn btn-info btn-sm">
                            <span class="fa fa-copy"></span> Compras
                        </button>
                        <button type="button" id="botonform8" class="btn btn-info btn-sm">
                            <span class="fa fa-truck"></span> Proveedores
                        </button>
                    @endif      
                    @if (can('editar-contabilidad-articulos', false) || can('actualizar-contabilidad-articulos', false))                                
                        <button type="button" id="botonform4" class="btn btn-info btn-sm">
                            <span class="fa fa-copy"></span> Datos contables
                        </button>
                    @endif
                    <button type="button" id="botonform5" class="btn btn-info btn-sm">
                        <span class="fa fa-copy"></span> Leyendas
                    </button>
                    <button type="button" id="botonform6" class="btn btn-info btn-sm">
                        <span class="fa fa-copy"></span> Archivos asociados
                    </button>
                    <button type="button" id="botonform7" class="btn btn-info btn-sm">
                        <span class="fa fa-copy"></span> Historia
                    </button>  
                </div>                
                <div class="card-body" style="padding-bottom: 0; padding-top: 5px;">
                    @include('stock.articulo.form')
                    @include('stock.articulo.form2')
                    @include('stock.articulo.form3')
                    @include('stock.articulo.form4')
                    @include('stock.articulo.form5')
                    @include('stock.articulo.form6')
                    @include('stock.articulo.form7')
                    @if (can('editar-compras-articulos', false) || can('actualizar-compras-articulos', false))
                        @include('stock.articulo.form8')
                    @endif
                </div>
                <div class="card-footer">
                	<div class="row">
                        @include('includes.boton-form-editar')
            		</div>
            	</div>
            </form>
        </div>
    </div>
</div>
@include('includes.contable.modalconsultacuentacontable')
@include('stock.formula_articulo.partials.modal_ver_formula_articulo')
@if (can('editar-compras-articulos', false) || can('actualizar-compras-articulos', false))
@include('includes.compras.modalconsultaproveedor')
@endif
@endsection
