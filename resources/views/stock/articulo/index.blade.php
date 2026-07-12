@extends("theme.$theme.layout")
@section('titulo')
Art&iacute;culos
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/includes/listado-filtros.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/stock/articulo/filtro.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/configuracion/salida.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/configuracion/configurar_salida.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/configuracion/modeloetiqueta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/configuracion/configurar_modeloetiqueta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/stock/articulo/consulta-precios.js")}}" type="text/javascript"></script>
@if (can('imprimir-articulos-qr', false))
<script src="{{ asset('assets/pages/scripts/stock/articulo/etiqueta-imprimiendo.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/articulo/etiqueta-cantidad.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/articulo/etiqueta-npu.js') }}" type="text/javascript"></script>
@endif
@if (\App\Support\Stock\MovimientosArticuloDepositoSupport::puedeConsultar())
@include('includes.stock.kardex_deposito_scripts')
<script src="{{ asset('assets/pages/scripts/stock/recuento/movimientos_articulo.js') }}" type="text/javascript"></script>
@endif
@if (\App\Support\Stock\RecepcionProveedorArticuloConsultaSupport::puedeConsultar())
<script src="{{ asset('assets/pages/scripts/stock/articulo/consulta-recepciones.js') }}" type="text/javascript"></script>
@endif

<script>
window.seteoSalidaPrograma = @json(\App\Support\Configuracion\SeteoSalidaProgramaSupport::STOCK_ARTICULO);
window.seteoModeloEtiquetaPrograma = @json(\App\Support\Configuracion\SeteoSalidaProgramaSupport::STOCK_ARTICULO);
window.seteoSalidaConfigurarUrl = @json(route('configurar_salida', ['programa' => ':programa']));

function checkState(index){
}
</script>

@endsection

<?php use App\Helpers\biblioteca;
use App\Support\Stock\ArticuloListadoFiltros; ?>

@section('contenido')
@php
    $retornoListadoQuery = \App\Support\Listado\QueryRetornoListado::retornoLinksDesdeFiltrosQuery($filtrosQuery ?? []);
@endphp
<meta name="csrf-token" content="{{ csrf_token() }}" />
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Art&iacute;culos</h3>
                @include('includes.configurar-salida')
                @include('includes.configurar-modeloetiqueta')
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-articulo',
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => ArticuloListadoFiltros::tieneCriteriosAplicados($filtros ?? []),
                        'limpiarUrl' => route('articulo'),
                        'placeholder' => 'Búsqueda rápida (tolera errores de tipeo)…',
                        'toggleTarget' => '#panel-filtros-articulo',
                        'toggleId' => 'btn-toggle-filtros-articulo',
                        'inputId' => 'filtro_valor',
                        'nuevoRegistroUrl' => route('crear_articulo', $retornoListadoQuery),
                        'nuevoRegistroCan' => 'crear-articulos',
                    ])
    				<a href="#" onclick="configurarSalida()" class="btn btn-outline-secondary btn-sm ml-1">
						<i class="fa fa-fw fa-cog"></i> Configura salida
					</a>
    				<a href="#" onclick="configurarModeloEtiqueta()" class="btn btn-success btn-sm ml-1">
						<i class="fa fa-fw fa-print"></i> Configura etiqueta
					</a>
                </div>
            </div>
            <form method="get" action="{{ route('articulo') }}" id="form-filtros-articulo" class="mb-0">
                @include('stock.articulo.partials.filtros_listado', [
                    'limpiarUrl' => route('articulo'),
                ])
            </form>
            <div class="card-body table-responsive p-0">
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'lista_articulo',
                    'queryparams' => $filtrosQuery ?? [],
                ])
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead>
                        <tr>
                            <th>SKU</th>
                            <th>C&oacute;d. barra</th>
                            <th>Descripci&oacute;n</th>
                            <th>Unidad de Medida</th>
                            <th>Categoría</th>
                            <th>Tipo de Artículo</th>
                            <th>Uso</th>
                            <th>Nro.Parte</th>
                            <th>Ubic.Parte</th>
                            <th class="text-right" title="Saldo en Anita (stkdep) para artículos LAB con depósito de entrega">Saldo dep.</th>
                            <th>Facturable</th>
                            <th>Estado</th>
                            <th data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
						@foreach($articulos as $articulo)
    						<tr>
        						<td>
            						{{ $articulo->codigoarticulo ?? '' }}
                                    @if (!empty($articulo->fl_precio_promedio_transferencia))
                                        <span class="badge badge-info ml-1" title="Artículo TITO (transferencia contable)">TITO</span>
                                    @endif
        						</td>
        						<td>
            						{{ $articulo->codigobarra ?? '' }}
        						</td>
        						<td>
            						{{ $articulo->descripcion ?? '' }}
        						</td>
        						<td>
            						{{ $articulo->nombreunidadmedida ?? '' }}
        						</td>
        						<td>
            						{{ $articulo->nombrecategoria ?? '' }}
        						</td>
        						<td>
            						{{ $articulo->nombretipoarticulo ?? '' }}
        						</td>
                                <td>
                                    {{ $articulo->nombreusoarticulo ?? '' }}
                                </td>
                                <td>{{$articulo->numeroparte ?? ''}}</td>
                                <td>{{$articulo->ubicacionparte ?? ''}}</td>
                                <td class="text-right">
                                    @if(isset($saldosStkdep[$articulo->id]))
                                        {{ number_format($saldosStkdep[$articulo->id], 2, ',', '.') }}
                                    @endif
                                </td>
                                <td>
                                    {{ ($articulo->nofactura == '0' ? 'Facturable' : ($articulo->nofactura == '1' ? 'No facturable' : '' )) }}
                                </td>
                                <td>{{ $articulo->estado }}</td>
                            <td>
                       			@if (can('editar-articulos', false))
                                	<a href="{{route('editar_articulo', ['id' => $articulo->id] + $retornoListadoQuery)}}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                        <i class="fa fa-edit"></i>
                                	</a>
								@endif
                       			@if (can('imprimir-articulos-qr', false))
          							@if((string)($articulo->numeroparte ?? '0') === '1')
                                	<button type="button"
                                	    class="btn-accion-tabla btn-imprimir-etiqueta-npu tooltipsC"
                                	    title="Imprimir etiqueta NPU"
                                	    data-articulo-id="{{ $articulo->id }}"
                                	    data-articulo-sku="{{ $articulo->codigoarticulo ?? $articulo->sku ?? '' }}"
                                	    data-articulo-descripcion="{{ $articulo->descripcion ?? '' }}">
                                        <i class="fa fa-qrcode"></i>
                                	</button>
          							@else
                                	<button type="button"
                                	    class="btn-accion-tabla btn-imprimir-etiqueta-cantidad tooltipsC"
                                	    title="Imprimir etiqueta"
                                	    data-articulo-id="{{ $articulo->id }}"
                                	    data-articulo-sku="{{ $articulo->codigoarticulo ?? $articulo->sku ?? '' }}"
                                	    data-articulo-descripcion="{{ $articulo->descripcion ?? '' }}"
                                	    data-max-cantidad="{{ \App\Support\Stock\ArticuloEtiquetaNpuRangoSupport::MAX_ETIQUETAS }}">
                                        <i class="fa fa-qrcode"></i>
                                	</button>
          							@endif
								@endif
                       			@if (can('listar-precios', false) || can('listar-articulos', false))
                                	<button type="button"
                                	    class="btn-accion-tabla consultapreciosarticulo tooltipsC"
                                	    title="Consultar precios en listas de venta"
                                	    data-articulo-id="{{ $articulo->id }}"
                                	    data-articulo-sku="{{ $articulo->codigoarticulo ?? $articulo->sku ?? '' }}"
                                	    data-articulo-descripcion="{{ $articulo->descripcion ?? '' }}">
                                        <i class="fas fa-dollar-sign text-success"></i>
                                	</button>
								@endif
                       			@if (\App\Support\Stock\MovimientosArticuloDepositoSupport::puedeConsultar())
                                	<button type="button"
                                	    class="btn-accion-tabla btn-saldos-articulo tooltipsC"
                                	    title="Saldos por dep&oacute;sito"
                                	    data-articulo-id="{{ $articulo->id }}"
                                	    data-articulo-sku="{{ $articulo->codigoarticulo ?? $articulo->sku ?? '' }}"
                                	    data-articulo-descripcion="{{ $articulo->descripcion ?? '' }}">
                                        <i class="fa fa-warehouse text-secondary"></i>
                                	</button>
                                	<button type="button"
                                	    class="btn-accion-tabla btn-movimientos-stock-articulo tooltipsC"
                                	    title="Kardex de stock"
                                	    data-articulo-id="{{ $articulo->id }}"
                                	    data-articulo-sku="{{ $articulo->codigoarticulo ?? $articulo->sku ?? '' }}"
                                	    data-articulo-descripcion="{{ $articulo->descripcion ?? '' }}"
                                	    data-deposito-id="{{ $articulo->depositoentrega_id ?? '' }}">
                                        <i class="fa fa-list-alt text-info"></i>
                                	</button>
								@endif
                       			@if (\App\Support\Stock\RecepcionProveedorArticuloConsultaSupport::puedeConsultar())
                                	<button type="button"
                                	    class="btn-accion-tabla btn-recepciones-articulo tooltipsC"
                                	    title="Recepciones de proveedor"
                                	    data-articulo-id="{{ $articulo->id }}"
                                	    data-articulo-sku="{{ $articulo->codigoarticulo ?? $articulo->sku ?? '' }}"
                                	    data-articulo-descripcion="{{ $articulo->descripcion ?? '' }}">
                                        <i class="fa fa-truck text-primary"></i>
                                	</button>
								@endif
                       			@if (can('borrar-articulos', false))
                                <form action="{{route('eliminar_articulo', ['id' => $articulo->id])}}" class="d-inline form-eliminar" method="POST">
                                    @csrf @method("delete")
                                    <button type="submit" class="btn-accion-tabla eliminar tooltipsC" title="Eliminar este registro">
                                        <i class="fa fa-times-circle text-danger"></i>
                                    </button>
                                </form>
								@endif
                            </td>
                        	</tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
{{ $articulos->appends($filtrosQuery ?? [])->links() }}
@include('includes.stock.modalconsultaprecioarticulo')
@if (can('imprimir-articulos-qr', false))
@include('includes.stock.modaletiquetanpuarticulo')
@include('includes.stock.modaletiquetacantidadarticulo')
@include('includes.proceso_overlay_aviso', [
    'overlayId' => 'articulo-etiqueta-imprimiendo-overlay',
    'tituloId' => 'articulo-etiqueta-imprimiendo-titulo',
    'subtituloId' => 'articulo-etiqueta-imprimiendo-subtitulo',
    'titulo' => 'Imprimiendo etiqueta…',
    'subtitulo' => 'Por favor espere. Se está enviando la etiqueta a la impresora.',
])
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
@endsection
