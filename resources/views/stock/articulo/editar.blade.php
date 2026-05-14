@extends("theme.$theme.layout")
@section('titulo')
    Editar Art&iacute;culo
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/stock/articulo/contable.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/stock/articulo/crear.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/stock/articulo/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/contable/cuentacontable/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/admin/crear.js")}}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-danger">
            <div class="card-header">
                <h3 class="card-title">Editar Art&iacute;culo Id: {{$producto->id}}</h3>
                <div class="card-tools">
                    <a href="{{route('articulo')}}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                        @if (can('cambiar-estado-articulos', false))
                            <button type="submit" onclick="anulaArticulo()" id="anulaarticulo" class="btn btn-warning">
                                <i class="fa fa-fw fa-cross"></i>
                                Inactivar el Artículo
                            </button>
                        @else
                            <input type="hidden" id="anulaarticulo" value="">
                        @endif                    
                </div>
            </div>
            <form action="{{route('actualizar_articulo', ['id' => $producto->id])}}" enctype="multipart/form-data" id="form-general" class="form-horizontal form--label-right" method="POST" autocomplete="off" @if(empty($puedeActualizarArticulo)) onsubmit="return false;" @endif>
                @csrf @method("put")
                <input type="hidden" id="articulo_id" name="articulo_id" class="form-control" value="{{ $producto->id }}" />
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
                @include('stock.articulo.form')
                @include('stock.articulo.form2')
                @include('stock.articulo.form3')
                @include('stock.articulo.form4')
                @include('stock.articulo.form5')
                @include('stock.articulo.form6')
                @include('stock.articulo.form7')                
                <div class="card-footer">
                    <div class="row">
                        @if(!empty($puedeActualizarArticulo))
                            @include('includes.boton-form-editar')
                        @else
                            <div class="col-lg-12 text-center">
                                <a href="{{ route('articulo') }}" class="btn btn-secondary">Salir</a>
                            </div>
                        @endif
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@include('includes.contable.modalconsultacuentacontable')
@include('stock.formula_articulo.partials.modal_ver_formula_articulo')
@if (can('listar-formula-articulo', false) || can('listar-articulos', false))
<script src="{{ asset('assets/pages/scripts/stock/articulo/formula-modal.js') }}" type="text/javascript"></script>
@endif
@endsection
