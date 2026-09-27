@extends("theme.$theme.layout")
@section('titulo')
Art&iacute;culos
@endsection

@section("styles")
<link rel="stylesheet" href="{{ asset('assets/css/listado-workbench.css') }}?v={{ filemtime(public_path('assets/css/listado-workbench.css')) }}">
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>
@php
    $qbeGruposJs = public_path('assets/pages/scripts/listado/workbench-qbe-grupos.js');
    $ordenJs = public_path('assets/pages/scripts/listado/workbench-orden.js');
    $agruparJs = public_path('assets/pages/scripts/listado/workbench-agrupar.js');
    $disenadorJs = public_path('assets/pages/scripts/listado/workbench-disenador-preview.js');
    $vistaGuardarJs = public_path('assets/pages/scripts/listado/workbench-vista-guardar.js');
    $productoWorkbenchJs = public_path('assets/pages/scripts/stock/product/workbench.js');
@endphp
<script src="{{ asset('assets/pages/scripts/listado/workbench-qbe-grupos.js') }}?v={{ file_exists($qbeGruposJs) ? filemtime($qbeGruposJs) : time() }}"></script>
<script src="{{ asset('assets/pages/scripts/listado/workbench-orden.js') }}?v={{ file_exists($ordenJs) ? filemtime($ordenJs) : time() }}"></script>
<script src="{{ asset('assets/pages/scripts/listado/workbench-agrupar.js') }}?v={{ file_exists($agruparJs) ? filemtime($agruparJs) : time() }}"></script>
<script src="{{ asset('assets/pages/scripts/listado/workbench-disenador-preview.js') }}?v={{ file_exists($disenadorJs) ? filemtime($disenadorJs) : time() }}"></script>
<script src="{{ asset('assets/pages/scripts/listado/workbench-vista-guardar.js') }}?v={{ file_exists($vistaGuardarJs) ? filemtime($vistaGuardarJs) : time() }}"></script>
<script src="{{ asset('assets/pages/scripts/stock/product/workbench.js') }}?v={{ file_exists($productoWorkbenchJs) ? filemtime($productoWorkbenchJs) : time() }}"></script>
<script src="{{ asset('assets/pages/scripts/stock/listaprecio/consulta.js') }}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/stock/articulo/consulta-precios.js")}}" type="text/javascript"></script>
@if (can('listar-reporte-historial-precios-compra', false))
<script src="{{ asset('assets/pages/scripts/stock/articulo/consulta-historial-precios.js') }}" type="text/javascript"></script>
@endif
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
    $sortActual = $filtros['sort'][0]['campo'] ?? '';
    $sortDir = $filtros['sort'][0]['dir'] ?? 'asc';
    $urlOrdenProducto = function (string $col) use ($filtrosQuery, $sortActual, $sortDir) {
        $q = $filtrosQuery ?? [];
        unset($q['sort'], $q['group']);
        $q['sort'] = [[
            'campo' => $col,
            'dir' => ($sortActual === $col && $sortDir === 'asc') ? 'desc' : 'asc',
        ]];

        return route('products.index', $q);
    };
    $marcaOrdenProducto = function (string $col) use ($sortActual, $sortDir) {
        if ($sortActual !== $col) {
            return '';
        }

        return $sortDir === 'asc' ? ' ↑' : ' ↓';
    };
    $limpiarUrl = route('products.index', ['filtro_limpiar' => 1]);
@endphp
<meta name="csrf-token" content="{{ csrf_token() }}" />
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info lw-workbench shadow-sm">
            <div class="card-header lw-header d-flex flex-wrap align-items-center justify-content-between">
                <h3 class="card-title mb-0">
                    <i class="fa fa-cube mr-1"></i> Art&iacute;culos
                    <small class="ml-2" style="opacity:.85;font-weight:400;">Workbench · consulta multi-campo</small>
                </h3>
                <div class="card-tools ml-auto d-flex flex-wrap align-items-center justify-content-end" style="gap:.4rem;">
                    <span id="container-button-state">
                        @if (can('cambiar-estado-combinaciones', false))
                            <button type="button" class="btn btn-outline-light btn-sm" onclick="checkState(0)">Inactivar combinaciones</button>
                        @endif
                    </span>
                    @if (can('crear-articulos-disenio', false))
                        <a href="{{ route('product.create', $retornoListadoQuery) }}" class="btn btn-light btn-sm">
                            <i class="fa fa-plus"></i> Nuevo art&iacute;culo
                        </a>
                    @endif
                </div>
            </div>
            @if (! ($workbenchListo ?? false))
                <div class="alert alert-warning lw-aviso-migracion mb-0">
                    <strong>Migración pendiente.</strong>
                    Para vistas y configuración de grilla hace falta la tabla <code>listado_vista</code>.
                </div>
            @endif
            <form method="get" action="{{ route('products.index') }}" id="form-filtros-producto-ferli" class="mb-0">
                <input type="hidden" name="filtro_busqueda_rapida" id="filtro_busqueda_rapida" value="">
                <input type="hidden" name="filtro_modo" id="filtro_modo" value="{{ $filtros['modo'] ?? 'todos' }}">
                <input type="hidden" name="filtro_valor" id="filtro_valor" value="{{ $filtros['valor'] ?? '' }}">
                <input type="hidden" name="columnas" id="lw_columnas_csv" value="{{ implode(',', $columnasVisibles ?? []) }}">
                @if (($filtros['estado'] ?? ArticuloFerliListadoFiltros::ESTADO_ACTIVO) === '')
                    <input type="hidden" name="filtro_estado" value="TODOS">
                @elseif (($filtros['estado'] ?? ArticuloFerliListadoFiltros::ESTADO_ACTIVO) !== ArticuloFerliListadoFiltros::ESTADO_ACTIVO)
                    <input type="hidden" name="filtro_estado" value="{{ $filtros['estado'] }}">
                @endif
                @if (($filtros['canal'] ?? '') !== '')
                    <input type="hidden" name="filtro_canal" value="{{ $filtros['canal'] }}">
                @endif
                @if (($filtros['estado_comb'] ?? ArticuloFerliListadoFiltros::ESTADO_COMB_ACTIVAS) !== ArticuloFerliListadoFiltros::ESTADO_COMB_ACTIVAS)
                    <input type="hidden" name="estado_comb" value="{{ $filtros['estado_comb'] }}">
                @endif
                @if ($vistaActiva ?? null)
                    <input type="hidden" name="vista_id" value="{{ $vistaActiva->id }}">
                @endif
                <div class="lw-toolbar">
                    <div class="lw-toolbar-left">
                        <select id="lw-vista-select" class="form-control form-control-sm lw-vista-select"
                                data-base-url="{{ route('products.index') }}"
                                title="Vistas guardadas"
                                @if (! ($workbenchListo ?? false)) disabled @endif>
                            <option value="">Vista est&aacute;ndar</option>
                            @foreach (($vistasListado ?? []) as $vista)
                                <option value="{{ $vista->id }}" @if (($vistaActiva ?? null) && (int) $vistaActiva->id === (int) $vista->id) selected @endif>
                                    {{ $vista->nombre }}
                                    @if ($vista->es_default) ★ @endif
                                    @if ($vista->compartida) (compartida) @endif
                                </option>
                            @endforeach
                        </select>
                        <button type="button" class="btn btn-sm btn-primary" data-toggle="modal" data-target="#modal-lw-grilla"
                                @if (! ($workbenchListo ?? false)) disabled title="Requiere migraci&oacute;n" @endif>
                            <i class="fa fa-th"></i> Dise&ntilde;ar vista
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-info collapsed" data-toggle="collapse" data-target="#lw-qbe-panel" aria-expanded="false" aria-controls="lw-qbe-panel">
                            <i class="fa fa-filter"></i> QBE
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-toggle="modal" data-target="#modal-lw-etiquetas"
                                @if (! ($workbenchListo ?? false)) disabled title="Requiere migraci&oacute;n" @endif>
                            <i class="fa fa-font"></i> Defaults instalaci&oacute;n
                        </button>
                    </div>
                    <div class="lw-toolbar-right">
                        <input type="search" id="lw-search-rapida" class="form-control form-control-sm lw-search-rapida"
                               value="{{ ($filtros['modo'] ?? '') !== 'qbe' ? ($filtros['valor'] ?? '') : '' }}"
                               placeholder="B&uacute;squeda r&aacute;pida (Enter)&hellip;"
                               autocomplete="off">
                        <button type="button" id="btn-lw-buscar-rapida" class="btn btn-sm btn-primary">
                            <i class="fa fa-search"></i>
                        </button>
                        @if (ArticuloFerliListadoFiltros::tieneCriteriosTexto($filtros ?? []))
                            <a href="{{ $limpiarUrl }}" class="btn btn-sm btn-outline-warning">
                                <i class="fa fa-eraser"></i> Limpiar
                            </a>
                        @endif
                    </div>
                </div>
                @include('stock.product.partials.filtros_externos')
                @include('stock.product.partials.workbench_qbe')
            </form>
            <div class="px-3 pt-2">
                @include('includes.listado.workbench_cortes', ['cortes' => $cortes ?? []])
            </div>
            <div class="card-body table-responsive p-0">
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'lista_producto_ferli',
                    'queryparams' => $filtrosQuery ?? [],
                ])
                <table class="table table-striped table-bordered table-hover table-sm mb-0" id="tabla-paginada">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            @foreach (($columnasVisibles ?? []) as $keyColumna)
                                @include('stock.product.partials.workbench_th', ['key' => $keyColumna])
                            @endforeach
                            <th class="width80 text-nowrap" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
						@foreach($articulos as $articulo)
    						<tr>
                            @foreach (($columnasVisibles ?? []) as $keyColumna)
                                @include('stock.product.partials.workbench_celda', ['key' => $keyColumna])
                            @endforeach
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
                       			@if (can('listar-precios', false) || can('listar-articulos', false))
                                	<button type="button"
                                	    class="btn-accion-tabla consultapreciosarticulo tooltipsC"
                                	    title="Consultar precios en listas de venta"
                                	    data-articulo-id="{{ $articulo->id }}"
                                	    data-articulo-sku="{{ $articulo->stkm_articulo ?? '' }}"
                                	    data-articulo-descripcion="{{ $articulo->stkm_desc ?? '' }}">
                                        <i class="fas fa-dollar-sign text-success"></i>
                                	</button>
								@endif
                       			@if (can('listar-reporte-historial-precios-compra', false))
                                	<button type="button"
                                	    class="btn-accion-tabla btn-historial-precios-articulo tooltipsC"
                                	    title="Historial de precios de compra"
                                	    data-articulo-id="{{ $articulo->id }}">
                                        <i class="fa fa-chart-line text-success"></i>
                                	</button>
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
@include('includes.stock.modalconsultaprecioarticulo')
@include('includes.stock.modalconsultalistaprecio')
@if (can('listar-reporte-historial-precios-compra', false))
<input type="hidden" id="historial-precios-articulo-url" value="{{ route('reporte_historial_precios_articulo') }}">
@endif
@include('stock.articulo.partials.workbench_modales', [
    'rutaGuardarColumnas' => 'guardar_columnas_listado_producto_ferli',
    'rutaGuardarVista' => 'guardar_vista_listado_producto_ferli',
    'rutaEliminarVista' => 'eliminar_vista_listado_producto_ferli',
    'rutaPreview' => 'preview_workbench_producto_ferli',
    'rutaGuardarEtiquetas' => 'guardar_etiquetas_listado_producto_ferli',
])

@endsection
