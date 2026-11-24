@extends("theme.$theme.layout")
@section('titulo')
Art&iacute;culos
@endsection

@section("styles")
<link rel="stylesheet" href="{{ asset('assets/css/listado-workbench.css') }}?v={{ filemtime(public_path('assets/css/listado-workbench.css')) }}">
<link rel="stylesheet" href="{{ asset('assets/css/tabla-ancha-reporte.css') }}?v={{ @filemtime(public_path('assets/css/tabla-ancha-reporte.css')) ?: time() }}">
<style>
/* Vista estándar: Acciones normal. Con scroll (más columnas): sticky derecha. */
.lw-workbench .producto-ferli-grilla {
    --tabla-ancha-der: 15rem;
}
.lw-workbench .producto-ferli-grilla #tabla-paginada th.lw-col-acciones,
.lw-workbench .producto-ferli-grilla #tabla-paginada td.lw-col-acciones {
    width: var(--tabla-ancha-der);
    min-width: var(--tabla-ancha-der);
    max-width: var(--tabla-ancha-der);
    white-space: nowrap;
    overflow: visible;
    text-align: center;
    vertical-align: middle;
    padding-left: 0.1rem;
    padding-right: 0.1rem;
}
.lw-workbench .producto-ferli-grilla.producto-ferli-grilla--fit .col-fija-der-1 {
    position: static;
    right: auto;
    box-shadow: none;
    z-index: auto;
}
/* Fit: tabla al 100% (tabla-ancha trae width:max-content y empujaba Acciones). */
.lw-workbench .producto-ferli-grilla .lw-table-fit .tabla-ancha {
    width: 100% !important;
    min-width: 100% !important;
    max-width: 100%;
}
.lw-workbench .producto-ferli-grilla .lw-table-fit .tabla-ancha td.lw-col {
    max-width: 0;
}
.lw-workbench .producto-ferli-grilla #tabla-paginada .lw-acciones-inner {
    gap: 0;
}
.lw-workbench .producto-ferli-grilla #tabla-paginada .lw-acciones-inner .btn-accion-tabla {
    padding: 0 0.05rem;
    min-width: 1.35rem;
    min-height: 1.35rem;
    font-size: 0.85rem;
}
.lw-workbench .producto-ferli-grilla #tabla-paginada .lw-acciones-inner form {
    display: inline-flex;
    align-items: center;
    margin: 0;
    padding: 0;
    border: 0;
    flex: 0 0 auto;
}
</style>
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
    $columnasDndJs = public_path('assets/pages/scripts/listado/workbench-columnas-dnd.js');
    $tablaAnchaJs = public_path('assets/pages/scripts/admin/tabla-ancha-reporte.js');
@endphp
<script src="{{ asset('assets/pages/scripts/listado/workbench-qbe-grupos.js') }}?v={{ file_exists($qbeGruposJs) ? filemtime($qbeGruposJs) : time() }}"></script>
<script src="{{ asset('assets/pages/scripts/listado/workbench-orden.js') }}?v={{ file_exists($ordenJs) ? filemtime($ordenJs) : time() }}"></script>
<script src="{{ asset('assets/pages/scripts/listado/workbench-agrupar.js') }}?v={{ file_exists($agruparJs) ? filemtime($agruparJs) : time() }}"></script>
<script src="{{ asset('assets/pages/scripts/listado/workbench-disenador-preview.js') }}?v={{ file_exists($disenadorJs) ? filemtime($disenadorJs) : time() }}"></script>
<script src="{{ asset('assets/pages/scripts/listado/workbench-vista-guardar.js') }}?v={{ file_exists($vistaGuardarJs) ? filemtime($vistaGuardarJs) : time() }}"></script>
<script src="{{ asset('assets/pages/scripts/stock/product/workbench.js') }}?v={{ file_exists($productoWorkbenchJs) ? filemtime($productoWorkbenchJs) : time() }}"></script>
<script src="{{ asset('assets/pages/scripts/listado/workbench-columnas-dnd.js') }}?v={{ file_exists($columnasDndJs) ? filemtime($columnasDndJs) : time() }}"></script>
<script src="{{ asset('assets/pages/scripts/admin/tabla-ancha-reporte.js') }}?v={{ file_exists($tablaAnchaJs) ? filemtime($tablaAnchaJs) : time() }}"></script>
<script src="{{ asset('assets/pages/scripts/stock/listaprecio/consulta.js') }}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/stock/articulo/consulta-precios.js")}}" type="text/javascript"></script>
@if (\App\Support\Stock\MovimientosArticuloDepositoSupport::puedeConsultar())
@include('includes.stock.kardex_deposito_scripts')
<script src="{{ asset('assets/pages/scripts/stock/recuento/movimientos_articulo.js') }}" type="text/javascript"></script>
@if (\App\Support\Stock\ArticuloKardexCombinacionSupport::uiActiva())
<script src="{{ asset('assets/pages/scripts/stock/articulo/kardex-combinacion-ferli.js') }}" type="text/javascript"></script>
@endif
@endif
@if (\App\Support\Stock\RecepcionProveedorArticuloConsultaSupport::puedeConsultar())
<script src="{{ asset('assets/pages/scripts/stock/articulo/consulta-recepciones.js') }}" type="text/javascript"></script>
@endif
@if (can('listar-reporte-historial-precios-compra', false))
<script src="{{ asset('assets/pages/scripts/stock/articulo/consulta-historial-precios.js') }}" type="text/javascript"></script>
@endif
<script>
function checkState(index){
  var confirmar = confirm("¿Inactivar la fábrica de todas las combinaciones?\n\nFab. on pasa a Fab. off en todas.\nEl local (Loc. on / Loc. off) no se modifica.");
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
    $camposOrdenablesTheadBarra = \App\Support\Stock\ArticuloFerliListadoFiltros::camposOrdenables();
    $ordenActualThead = \App\Support\Listado\ListadoOrdenamientoSupport::normalizar(
        $filtros['sort'] ?? [],
        $camposOrdenablesTheadBarra
    );
    $qsQuitarOrden = $filtrosQuery ?? [];
    unset($qsQuitarOrden['sort'], $qsQuitarOrden['page']);
    $qsQuitarOrden['quitar_orden'] = 1;
    $urlQuitarOrden = route('products.index', $qsQuitarOrden);

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
                            <button type="button" class="btn btn-outline-light btn-sm" onclick="checkState(0)" title="Pasa todas las combinaciones de Fab. on a Fab. off. El local no se toca.">Inactivar f&aacute;brica (todas)</button>
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
                        @if (($ordenActualThead ?? []) !== [])
                            <a href="{{ $urlQuitarOrden }}" class="btn btn-sm btn-outline-secondary" title="Vuelve al orden de instalación y lo saca de la vista">
                                <i class="fa fa-undo"></i> Orden estándar
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
            @php
                use App\Support\Listado\ListadoGrillaConfigSupport as LwGrilla;
                $layoutPorKey = [];
                foreach (($grillaLayout ?? []) as $filaLayout) {
                    $layoutPorKey[$filaLayout['key']] = $filaLayout;
                }
                $layoutPantalla = LwGrilla::escalarAnchosParaPantalla($grillaLayout ?? []);
                $layoutPantallaPorKey = [];
                foreach ($layoutPantalla as $filaP) {
                    $layoutPantallaPorKey[$filaP['key']] = $filaP;
                }
                $sumaAnchosPantalla = max(1, LwGrilla::sumaAnchosVisibles($layoutPantalla));
                // Vista estándar Ferli (~8 cols): fit. Sticky Acciones solo si hay overflow.
                $usaScrollHorizontal = count($columnasVisibles ?? []) > 14
                    || $sumaAnchosPantalla > (LwGrilla::ANCHO_PRESUPUESTO_PANTALLA + 200);
                $anchoAcciones = 240;
                $pctDatosDisponible = $usaScrollHorizontal
                    ? 100.0
                    : round(100 * $sumaAnchosPantalla / ($sumaAnchosPantalla + $anchoAcciones), 2);
            @endphp
            <div class="tabla-ancha-grilla producto-ferli-grilla {{ $usaScrollHorizontal ? '' : 'producto-ferli-grilla--fit' }}" data-tabla-ancha>
                <div class="tabla-ancha-scroll-top" hidden>
                    <div class="tabla-ancha-scroll-top-inner"></div>
                </div>
                <div class="tabla-ancha-wrap card-body p-0 lw-table-wrap {{ $usaScrollHorizontal ? 'table-responsive lw-table-scroll' : 'lw-table-fit' }}">
                <table class="tabla-ancha table table-striped table-bordered table-hover table-sm mb-0 lw-tabla-grilla" id="tabla-paginada">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            @foreach (($columnasVisibles ?? []) as $keyColumna)
                                @php
                                    $cfgPantalla = $layoutPantallaPorKey[$keyColumna] ?? ($layoutPorKey[$keyColumna] ?? null);
                                    $ancho = (int) ($cfgPantalla['ancho'] ?? 100);
                                    $tituloCol = $etiquetasColumnas[$keyColumna] ?? ($catalogoColumnas[$keyColumna]['label'] ?? $keyColumna);
                                    if ($usaScrollHorizontal) {
                                        $minLegible = LwGrilla::anchoMinimoLegible(
                                            $keyColumna,
                                            $catalogoColumnas[$keyColumna] ?? [],
                                            $tituloCol
                                        );
                                        $styleCol = 'width:'.$ancho.'px;min-width:'.$minLegible.'px;';
                                    } else {
                                        $pct = round(($ancho / $sumaAnchosPantalla) * $pctDatosDisponible, 2);
                                        $styleCol = 'width:'.$pct.'%;min-width:0;';
                                    }
                                @endphp
                                @include('stock.product.partials.workbench_th', [
                                    'key' => $keyColumna,
                                    'styleCol' => $styleCol,
                                ])
                            @endforeach
                            <th class="lw-col-acciones col-fija-der-1" data-orderable="false"
                                style="width:{{ $anchoAcciones }}px;min-width:{{ $anchoAcciones }}px;max-width:{{ $anchoAcciones }}px;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
						@foreach($articulos as $articulo)
    						<tr>
                            @foreach (($columnasVisibles ?? []) as $keyColumna)
                                @include('stock.product.partials.workbench_celda', [
                                    'key' => $keyColumna,
                                    'cfg' => $layoutPorKey[$keyColumna] ?? null,
                                ])
                            @endforeach
                            <td class="lw-col-acciones col-fija-der-1 text-nowrap"
                                style="width:{{ $anchoAcciones }}px;min-width:{{ $anchoAcciones }}px;max-width:{{ $anchoAcciones }}px;">
                                <div class="lw-acciones-inner">
								@if ($articulo->usoarticulo_id == 1)
                       				@if (can('editar-articulos-combinaciones', false))
          								<a class="btn-accion-tabla tooltipsC" href="{{ route('combinacion.index', ['id' => $articulo->id] + $retornoListadoQuery) }}" title="Combinaciones">
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
                       			@if (\App\Support\Stock\MovimientosArticuloDepositoSupport::puedeConsultar())
                                	<button type="button"
                                	    class="btn-accion-tabla btn-saldos-articulo tooltipsC"
                                	    title="Saldos por dep&oacute;sito"
                                	    data-articulo-id="{{ $articulo->id }}"
                                	    data-articulo-sku="{{ $articulo->stkm_articulo ?? '' }}"
                                	    data-articulo-descripcion="{{ $articulo->stkm_desc ?? '' }}">
                                        <i class="fa fa-warehouse text-secondary"></i>
                                	</button>
                                    @if (\App\Support\Stock\ArticuloKardexCombinacionSupport::uiActiva())
                                	<button type="button"
                                	    class="btn-accion-tabla btn-kardex-combinacion-ferli tooltipsC"
                                	    title="Kardex por combinaci&oacute;n y dep&oacute;sito"
                                	    data-articulo-id="{{ $articulo->id }}"
                                	    data-articulo-sku="{{ $articulo->stkm_articulo ?? '' }}"
                                	    data-articulo-descripcion="{{ $articulo->stkm_desc ?? '' }}">
                                        <i class="fa fa-list-alt text-info"></i>
                                	</button>
                                    @else
                                	<button type="button"
                                	    class="btn-accion-tabla btn-movimientos-stock-articulo tooltipsC"
                                	    title="Kardex de stock"
                                	    data-articulo-id="{{ $articulo->id }}"
                                	    data-articulo-sku="{{ $articulo->stkm_articulo ?? '' }}"
                                	    data-articulo-descripcion="{{ $articulo->stkm_desc ?? '' }}"
                                	    data-deposito-id="{{ $articulo->depositoentrega_id ?? '' }}">
                                        <i class="fa fa-list-alt text-info"></i>
                                	</button>
                                    @endif
								@endif
                       			@if (\App\Support\Stock\RecepcionProveedorArticuloConsultaSupport::puedeConsultar())
                                	<button type="button"
                                	    class="btn-accion-tabla btn-recepciones-articulo tooltipsC"
                                	    title="Recepciones de proveedor"
                                	    data-articulo-id="{{ $articulo->id }}"
                                	    data-articulo-sku="{{ $articulo->stkm_articulo ?? '' }}"
                                	    data-articulo-descripcion="{{ $articulo->stkm_desc ?? '' }}">
                                        <i class="fa fa-truck text-primary"></i>
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
                                </div>
                            </td>
                        	</tr>
                        @endforeach
                    </tbody>
                </table>
                </div>
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
@if (\App\Support\Stock\MovimientosArticuloDepositoSupport::puedeConsultar())
@include('includes.stock.modal_kardex_deposito')
@include('includes.stock.modal_saldos_articulo')
@if (\App\Support\Stock\ArticuloKardexCombinacionSupport::uiActiva())
@include('includes.stock.modal_kardex_combinacion')
@endif
<input type="hidden" id="recuento-movimientos-articulo-url" value="{{ route('recuento_movimientos_articulo') }}">
<input type="hidden" id="articulo-saldos-deposito-url" value="{{ route('articulo_saldos_deposito') }}">
@endif
@if (\App\Support\Stock\RecepcionProveedorArticuloConsultaSupport::puedeConsultar())
<input type="hidden" id="recepcion-proveedor-consulta-articulo-url" value="{{ route('recepcion_proveedor_consulta_articulo') }}">
@endif
@if (can('listar-reporte-historial-precios-compra', false))
<input type="hidden" id="historial-precios-articulo-url" value="{{ route('reporte_historial_precios_articulo') }}">
@endif
{{-- Reordenar/ocultar columnas en thead; agregar = Diseñar vista. --}}
@include('includes.listado.workbench_columnas_dnd', [
    'rutaGuardarColumnasDnd' => 'guardar_columnas_listado_producto_ferli',
    'paraQueryStringDnd' => [\App\Support\Stock\ArticuloFerliListadoFiltros::class, 'paraQueryString'],
    'filtrosDnd' => $filtros ?? [],
])
@include('stock.articulo.partials.workbench_modales', [
    'rutaGuardarColumnas' => 'guardar_columnas_listado_producto_ferli',
    'rutaGuardarVista' => 'guardar_vista_listado_producto_ferli',
    'rutaEliminarVista' => 'eliminar_vista_listado_producto_ferli',
    'rutaPreview' => 'preview_workbench_producto_ferli',
    'rutaGuardarEtiquetas' => 'guardar_etiquetas_listado_producto_ferli',
])

@endsection
