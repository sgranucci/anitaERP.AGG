@extends("theme.$theme.layout")
@section('titulo')
Art&iacute;culos
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/includes/listado-filtros.js")}}" type="text/javascript"></script>
<script>
function checkState(index){
  var confirmar = confirm("¿Desea inactivar combinaciones de forma masiva?");
  if(confirmar){

    var id = $("#producto_id").val();
    var token = $("meta[name='csrf-token']").attr("content");
    var estado = 'I';
    var data = "id="+id+"&estado="+estado+"&_token="+token;

    $.ajax({
        type: "POST",
        url: "{{ route('combinacion.updateStateAll') }}",
        data: data,
        success: function(response){
          window.location.reload();
        }
    });
  }
}
</script>
@endsection

<?php
use App\Helpers\biblioteca;
use App\Support\Stock\ArticuloFerliListadoFiltros;
?>

@section('contenido')
@php
    $retornoListadoQuery = $retornoQuery ?? \App\Support\Listado\QueryRetornoListado::retornoLinksDesdeFiltrosQuery($filtrosQuery ?? []);
@endphp
<meta name="csrf-token" content="{{ csrf_token() }}" />
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Art&iacute;culos</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-producto-ferli',
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => ArticuloFerliListadoFiltros::tieneCriteriosAplicados($filtros ?? []),
                        'limpiarUrl' => route('products.index', ['filtro_limpiar' => 1]),
                        'placeholder' => 'Búsqueda rápida (tolera errores de tipeo)…',
                        'toggleTarget' => '#panel-filtros-producto-ferli',
                        'toggleId' => 'btn-toggle-filtros-producto-ferli',
                        'inputId' => 'filtro_valor',
                        'nuevoRegistroUrl' => route('product.create', $retornoListadoQuery),
                        'nuevoRegistroCan' => 'crear-articulos-disenio',
                    ])
                    <span id="container-button-state" class="ml-1">
                        @if (can('cambiar-estado-combinaciones', false))
                            <button type="button" class="btn btn-outline-secondary btn-sm" style="color:white" onclick="checkState(0)">Inactivar combinaciones</button>
                        @endif
                    </span>
                </div>
            </div>
            <form method="get" action="{{ route('products.index') }}" id="form-filtros-producto-ferli" class="mb-0">
                @include('stock.product.partials.filtros_listado', [
                    'limpiarUrl' => route('products.index', ['filtro_limpiar' => 1]),
                ])
            </form>
            <div class="card-body py-2 border-bottom bg-white d-flex flex-wrap align-items-center justify-content-between">
                <div class="mb-1 mb-md-0">
                    @include('includes.exportar-tabla-queryparams', [
                        'ruta' => 'lista_producto_ferli',
                        'queryparams' => $filtrosQuery ?? [],
                    ])
                </div>
                <div class="mb-1 mb-md-0 ml-auto">
                    @include('stock.product.partials.filtros_externos')
                </div>
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-striped table-bordered table-hover table-sm mb-0" id="tabla-paginada">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th>C&oacute;digo</th>
                            <th>Descripci&oacute;n</th>
                            <th>Categor&iacute;a</th>
                            <th>Marca</th>
                            <th>L&iacute;nea</th>
                            <th>Facturable</th>
                            <th class="width80 text-nowrap" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
						@foreach($articulos as $articulo)
    						<tr>
        						<td>{{ $articulo->stkm_articulo ?? '' }}</td>
        						<td>{{ $articulo->stkm_desc ?? '' }}</td>
        						<td>{{ $articulo->stkm_agrupacion ?? '' }}</td>
        						<td>{{ $articulo->stkm_marca ?? '' }}</td>
        						<td>{{ $articulo->stkm_linea ?? '' }}</td>
                                <td>{{ $articulo->nofactura == '0' ? 'Facturable' : 'No facturable'}}</td>
                            <td class="text-nowrap">
								@if ($articulo->usoarticulo_id == 1)
                       				@if (can('editar-articulos-combinaciones', false))
          								<a class="btn-accion-tabla tooltipsC" href="{{ route('combinacion.index', ['id' => $articulo->id]) }}" title="Combinaciones">
                                            <i class="fa fa-layer-group text-primary"></i>
                                        </a>
									@endif
								@endif
                       			@if (can('editar-articulos-disenio', false))
          							<a class="btn-accion-tabla tooltipsC" href="{{ route('product.edit', ['id' => $articulo->id, 'tipo' => 'disenio'] + $retornoListadoQuery) }}" title="Diseño">
                                        <i class="fa fa-paint-brush text-info"></i>
                                    </a>
								@endif
                       			@if (can('editar-articulos-tecnica', false))
          							<a class="btn-accion-tabla tooltipsC" href="{{ route('product.edit', ['id' => $articulo->id, 'tipo' => 'tecnica'] + $retornoListadoQuery) }}" title="Técnica">
                                        <i class="fa fa-cogs text-secondary"></i>
                                    </a>
								@endif
                       			@if (can('editar-articulos-contaduria', false))
          							<a class="btn-accion-tabla tooltipsC" href="{{ route('product.edit', ['id' => $articulo->id, 'tipo' => 'contaduria'] + $retornoListadoQuery) }}" title="Contable">
                                        <i class="fa fa-calculator text-warning"></i>
                                    </a>
								@endif
                       			@if (can('imprimir-articulos-qr', false))
          							<a href="{{ route('product.download', ['sku' => $articulo->stkm_articulo, 'codigo' => 'TODO']) }}" class="btn-accion-tabla tooltipsC" title="Imprimir QR">
                                   		<i class="fa fa-qrcode"></i>
									</a>
								@endif
                       			@if (can('borrar-articulos', false))
                                <form action="{{route('product.delete', ['id' => $articulo->id])}}" class="d-inline form-eliminar" method="POST">
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
            @if (method_exists($articulos, 'links'))
                <div class="card-footer clearfix py-2">
                    <div class="float-left text-muted small pt-1">
                        @if ($articulos->total() > 0)
                            {{ $articulos->firstItem() }}–{{ $articulos->lastItem() }} de {{ $articulos->total() }}
                        @endif
                    </div>
                    <div class="float-right">
                        {{ $articulos->appends($filtrosQuery ?? [])->links() }}
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>

@endsection
