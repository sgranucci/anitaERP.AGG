@extends("theme.$theme.layout")
@section('titulo')
    Editar Art&iacute;culo
@endsection

@section("scripts")
<meta name="csrf-token" content="{{ csrf_token() }}">
<script src="{{asset("assets/pages/scripts/stock/articulo/contable.js")}}" type="text/javascript"></script>
<script src="{{asset('assets/pages/scripts/stock/articulo/crear.js')}}?v=20260607" type="text/javascript"></script>
@if (can('editar-compras-articulos', false) || can('actualizar-compras-articulos', false))
<script src="{{ asset('assets/pages/scripts/compras/proveedor/consulta.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/articulo/proveedores.js') }}?v=20260607c" type="text/javascript"></script>
@endif
<script src="{{asset("assets/pages/scripts/stock/articulo/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/contable/cuentacontable/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/admin/crear.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/stock/articulo/consulta-precios.js")}}" type="text/javascript"></script>
@if((string)($producto->numeroparte ?? '0') === '1')
<script src="{{ asset('assets/pages/scripts/stock/articulo/partes_unicas.js') }}?v=20260608" type="text/javascript"></script>
@endif
@if (\App\Support\Stock\MovimientosArticuloDepositoSupport::puedeConsultar())
<script src="{{ asset('assets/pages/scripts/stock/recuento/movimientos_articulo.js') }}" type="text/javascript"></script>
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
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-danger">
            <div class="card-header">
                <h3 class="card-title">
                    @if (! empty($soloConsulta) && empty($puedeActualizarArticulo))
                        Consultar
                    @else
                        Editar
                    @endif
                    Art&iacute;culo Id: {{ $producto->id }}&nbsp;{{ $producto->descripcion ?? '' }}
                </h3>
                <div class="card-tools">
                    @if (can('listar-precios', false) || can('listar-articulos', false))
                    <button type="button"
                        class="btn btn-light btn-sm consultapreciosarticulo tooltipsC font-weight-bold shadow-sm border"
                        title="Consultar precios en listas de venta"
                        data-articulo-id="{{ $producto->id }}"
                        data-articulo-sku="{{ $producto->sku ?? '' }}"
                        data-articulo-descripcion="{{ $producto->descripcion ?? '' }}">
                        <i class="fas fa-fw fa-dollar-sign text-primary"></i> Consultar precios
                    </button>
                    @endif
                    @if (\App\Support\Stock\MovimientosArticuloDepositoSupport::puedeConsultar())
                    <button type="button"
                        class="btn btn-secondary btn-sm btn-movimientos-stock-articulo tooltipsC"
                        title="Kardex de stock por dep&oacute;sito"
                        data-articulo-id="{{ $producto->id }}"
                        data-articulo-sku="{{ $producto->sku ?? '' }}"
                        data-articulo-descripcion="{{ $producto->descripcion ?? '' }}"
                        data-deposito-id="{{ $producto->depositoentrega_id ?? '' }}">
                        <i class="fa fa-fw fa-list-alt"></i> Kardex
                    </button>
                    @endif
                    @if (empty($ocultarVolver))
                    <a href="{{route('articulo')}}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                    @endif
                        @if (empty($soloConsulta) && can('cambiar-estado-articulos', false))
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
                @if (!empty($ocultarVolver))
                    <input type="hidden" name="origen" value="modal_consulta">
                @endif
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
                    @if((string)($producto->numeroparte ?? '0') === '1')
                    <button type="button" id="botonform9" class="btn btn-info btn-sm">
                        <span class="fa fa-barcode"></span> Partes únicas
                        @if(!empty($partesUnicasTotal))
                        <span class="badge badge-light">{{ $partesUnicasTotal }}</span>
                        @endif
                    </button>
                    @endif
                </div>
                <div class="@if(!empty($soloConsulta) && empty($puedeActualizarArticulo)) pe-none @endif" @if(!empty($soloConsulta) && empty($puedeActualizarArticulo)) style="opacity:.92" @endif>
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
                @if((string)($producto->numeroparte ?? '0') === '1')
                    @include('stock.articulo.form9_partes_unicas')
                @endif
                </div>
                <div class="card-footer">
                    <div class="row">
                        @if(!empty($soloConsulta))
                            <div class="col-lg-12 text-center">
                                @if(!empty($puedeActualizarArticulo))
                                    @include('includes.boton-form-editar')
                                @endif
                                <button type="button" class="btn btn-secondary @if(!empty($puedeActualizarArticulo)) ml-2 @endif" onclick="window.close()">Cerrar solapa</button>
                            </div>
                        @elseif(!empty($puedeActualizarArticulo))
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
@include('includes.stock.modalconsultaprecioarticulo')
@if (can('editar-compras-articulos', false) || can('actualizar-compras-articulos', false))
@include('includes.compras.modalconsultaproveedor')
@endif
@if (\App\Support\Stock\MovimientosArticuloDepositoSupport::puedeConsultar())
@include('includes.stock.modal_kardex_deposito')
<input type="hidden" id="recuento-movimientos-articulo-url" value="{{ route('recuento_movimientos_articulo') }}">
@endif
@endsection
