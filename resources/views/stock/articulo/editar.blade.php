@extends("theme.$theme.layout")
@section('titulo')
    Editar Art&iacute;culo
@endsection

@section("scripts")
<meta name="csrf-token" content="{{ csrf_token() }}">
<script src="{{asset("assets/pages/scripts/stock/articulo/contable.js")}}" type="text/javascript"></script>
<script src="{{asset('assets/pages/scripts/stock/articulo/crear.js')}}?v={{ @filemtime(public_path('assets/pages/scripts/stock/articulo/crear.js')) ?: time() }}" type="text/javascript"></script>
@if (can('editar-compras-articulos', false) || can('actualizar-compras-articulos', false))
<script src="{{ asset('assets/pages/scripts/compras/proveedor/consulta.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/articulo/proveedores.js') }}?v=20260607c" type="text/javascript"></script>
@endif
<script src="{{asset("assets/pages/scripts/stock/articulo/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/contable/cuentacontable/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/admin/crear.js")}}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/listaprecio/consulta.js') }}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/stock/articulo/consulta-precios.js")}}" type="text/javascript"></script>
@if (config('app.empresa') == 'INTERFORMING')
<script src="{{ asset('assets/pages/scripts/stock/sifab_maestro/consulta.js') }}?v=20260727" type="text/javascript"></script>
@endif
@if (config('app.empresa') == 'EL BIERZO')
<script src="{{ asset('assets/pages/scripts/stock/codigosenasa/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/stock/codigosenasa/consulta.js')) ?: time() }}" type="text/javascript"></script>
@endif
@if((string)($producto->numeroparte ?? '0') === '1')
<script src="{{ asset('assets/pages/scripts/stock/articulo/partes_unicas.js') }}?v=20260608" type="text/javascript"></script>
@endif
@if (\App\Support\Stock\MovimientosArticuloDepositoSupport::puedeConsultar())
@include('includes.stock.kardex_deposito_scripts')
<script src="{{ asset('assets/pages/scripts/stock/recuento/movimientos_articulo.js') }}" type="text/javascript"></script>
@endif
@if (\App\Support\Stock\RecepcionProveedorArticuloConsultaSupport::puedeConsultar())
<script src="{{ asset('assets/pages/scripts/stock/articulo/consulta-recepciones.js') }}" type="text/javascript"></script>
@endif
@if (can('listar-reporte-historial-precios-compra', false))
<script src="{{ asset('assets/pages/scripts/stock/articulo/consulta-historial-precios.js') }}" type="text/javascript"></script>
@endif
@if (can('listar-formula-articulo', false) || can('editar-formula-articulo', false) || can('listar-articulos', false))
<script src="{{ asset('assets/pages/scripts/stock/articulo/articulos-compra-insumo.js') }}" type="text/javascript"></script>
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
@if (\App\Support\Stock\TransferenciaMercaderiaRepararCostosSupport::puedeRecalcularDesdeArticulo())
<script src="{{ asset('assets/pages/scripts/stock/articulo/recalcular-transferencias-formula.js') }}?v=20260729a" type="text/javascript"></script>
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
        <div class="card card-primary">
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
                        class="btn btn-secondary btn-sm btn-saldos-articulo tooltipsC"
                        title="Saldos por dep&oacute;sito"
                        data-articulo-id="{{ $producto->id }}"
                        data-articulo-sku="{{ $producto->sku ?? '' }}"
                        data-articulo-descripcion="{{ $producto->descripcion ?? '' }}">
                        <i class="fa fa-fw fa-warehouse"></i> Saldos
                    </button>
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
                    @if (\App\Support\Stock\RecepcionProveedorArticuloConsultaSupport::puedeConsultar())
                    <button type="button"
                        class="btn btn-secondary btn-sm btn-recepciones-articulo tooltipsC"
                        title="Recepciones de proveedor con este art&iacute;culo"
                        data-articulo-id="{{ $producto->id }}"
                        data-articulo-sku="{{ $producto->sku ?? '' }}"
                        data-articulo-descripcion="{{ $producto->descripcion ?? '' }}">
                        <i class="fa fa-fw fa-truck"></i> Recepciones
                    </button>
                    @endif
                    @if (can('listar-reporte-historial-precios-compra', false))
                    <button type="button"
                        class="btn btn-secondary btn-sm btn-historial-precios-articulo tooltipsC"
                        title="Historial de precios de compra (último, anterior, variación)"
                        data-articulo-id="{{ $producto->id }}">
                        <i class="fa fa-fw fa-chart-line"></i> Hist. precios compra
                    </button>
                    @endif
                    @if (empty($ocultarVolver))
                    <a href="{{ $volverListadoUrl }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                    @endif
                        @if (empty($soloConsulta) && can('cambiar-estado-articulos', false))
                            <button type="submit" onclick="anulaArticulo()" id="anulaarticulo" class="btn btn-warning btn-sm">
                                <i class="fa fa-fw fa-ban"></i>
                                Inactivar el Artículo
                            </button>
                        @else
                            <input type="hidden" id="anulaarticulo" value="">
                        @endif
                </div>
            </div>
            <form action="{{ route('actualizar_articulo', ['id' => $producto->id] + ($filtrosQuery ?? [])) }}" enctype="multipart/form-data" id="form-general" class="form-horizontal form--label-right" method="POST" autocomplete="off" @if(empty($puedeActualizarArticulo)) onsubmit="return false;" @endif>
                @csrf @method("put")
                @if (!empty($ocultarVolver))
                    <input type="hidden" name="origen" value="modal_consulta">
                @endif
                <input type="hidden" id="articulo_id" name="articulo_id" class="form-control" value="{{ $producto->id }}" />
                @include('stock.articulo.partials.tabs_header', [
                    'tabsArticuloActiva' => 'datos',
                    'mostrarPartesUnicas' => (string) ($producto->numeroparte ?? '0') === '1',
                    'partesUnicasTotal' => $partesUnicasTotal ?? null,
                    'producto' => $producto,
                ])
                <div class="@if(!empty($soloConsulta) && empty($puedeActualizarArticulo)) pe-none @endif" @if(!empty($soloConsulta) && empty($puedeActualizarArticulo)) style="opacity:.92" @endif>
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
                @if((string)($producto->numeroparte ?? '0') === '1')
                    @include('stock.articulo.form9_partes_unicas')
                @endif
                </div>
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
                                <a href="{{ $volverListadoUrl }}" class="btn btn-secondary">Salir</a>
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
@include('includes.stock.modalconsultalistaprecio')
@if (can('editar-compras-articulos', false) || can('actualizar-compras-articulos', false))
@include('includes.compras.modalconsultaproveedor')
@endif
@if (config('app.empresa') == 'INTERFORMING')
@include('includes.stock.modalconsultasifabmaestro')
@endif
@if (config('app.empresa') == 'EL BIERZO')
@include('includes.stock.modalconsultacodigosenasa')
@endif
@if (\App\Support\Stock\MovimientosArticuloDepositoSupport::puedeConsultar())
@include('includes.stock.modal_kardex_deposito')
@include('includes.stock.modal_saldos_articulo')
<input type="hidden" id="recuento-movimientos-articulo-url" value="{{ route('recuento_movimientos_articulo') }}">
<input type="hidden" id="articulo-saldos-deposito-url" value="{{ route('articulo_saldos_deposito') }}">
@endif
@if (\App\Support\Stock\RecepcionProveedorArticuloConsultaSupport::puedeConsultar())
<input type="hidden" id="recepcion-proveedor-consulta-articulo-url" value="{{ route('recepcion_proveedor_consulta_articulo') }}">
@endif
@if (can('listar-reporte-historial-precios-compra', false))
<input type="hidden" id="historial-precios-articulo-url" value="{{ route('reporte_historial_precios_articulo') }}">
@endif
@if (can('listar-formula-articulo', false) || can('editar-formula-articulo', false) || can('listar-articulos', false))
@include('stock.formula_articulo.partials.modal_articulos_compra_insumo')
<input type="hidden" id="articulos-compra-por-insumo-url" value="{{ route('articulos_compra_por_insumo_formula', ['articulo_id' => 0]) }}">
@endif
@if (\App\Support\Stock\TransferenciaMercaderiaRepararCostosSupport::puedeRecalcularDesdeArticulo())
@include('stock.articulo.partials.modal_recalcular_transferencias_formula')
<input type="hidden" id="articulo-preview-recalcular-tra-formula-url" value="{{ route('articulo_preview_recalcular_transferencias_formula', ['id' => $producto->id ?? 0]) }}">
<input type="hidden" id="articulo-aplicar-recalcular-tra-formula-url" value="{{ route('articulo_aplicar_recalcular_transferencias_formula', ['id' => $producto->id ?? 0]) }}">
@endif
@endsection
