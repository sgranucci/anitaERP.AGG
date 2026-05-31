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
<script src="{{asset("assets/pages/scripts/configuracion/configurar_modeloetiqueta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/stock/articulo/consulta-precios.js")}}" type="text/javascript"></script>

<script>
var url = "{{ route('configurar_salida', ['programa' => ':programa']) }}";

function checkState(index){
}
</script>

@endsection

<?php use App\Helpers\biblioteca;
use App\Support\Stock\ArticuloListadoFiltros; ?>

@section('contenido')
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
                        'nuevoRegistroUrl' => route('crear_articulo'),
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
                                	<a href="{{route('editar_articulo', ['id' => $articulo->id])}}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                        <i class="fa fa-edit"></i>
                                	</a>
								@endif
                       			@if (can('imprimir-articulos-qr', false))
          							<a href="{{route('listar_etiqueta_articulo', ['id' => $articulo->id])}}" class="btn-accion-tabla tooltipsC" title="Imprimir QR">
                                   		<i class="fa fa-qrcode"></i>
									</a>
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
@endsection
