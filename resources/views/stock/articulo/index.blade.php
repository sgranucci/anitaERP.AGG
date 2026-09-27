@extends("theme.$theme.layout")
@section('titulo')
Art&iacute;culos
@endsection

@section("styles")
<link rel="stylesheet" href="{{ asset('assets/css/listado-workbench.css') }}?v={{ filemtime(public_path('assets/css/listado-workbench.css')) }}">
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/includes/listado-filtros.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/stock/articulo/filtro.js")}}" type="text/javascript"></script>
@php
    $qbeGruposJs = public_path('assets/pages/scripts/listado/workbench-qbe-grupos.js');
    $ordenJs = public_path('assets/pages/scripts/listado/workbench-orden.js');
    $agruparJs = public_path('assets/pages/scripts/listado/workbench-agrupar.js');
    $disenadorJs = public_path('assets/pages/scripts/listado/workbench-disenador-preview.js');
    $vistaGuardarJs = public_path('assets/pages/scripts/listado/workbench-vista-guardar.js');
    $articuloWorkbenchJs = public_path('assets/pages/scripts/stock/articulo/workbench.js');
@endphp
<script src="{{ asset('assets/pages/scripts/listado/workbench-qbe-grupos.js') }}?v={{ file_exists($qbeGruposJs) ? filemtime($qbeGruposJs) : time() }}"></script>
<script src="{{ asset('assets/pages/scripts/listado/workbench-orden.js') }}?v={{ file_exists($ordenJs) ? filemtime($ordenJs) : time() }}"></script>
<script src="{{ asset('assets/pages/scripts/listado/workbench-agrupar.js') }}?v={{ file_exists($agruparJs) ? filemtime($agruparJs) : time() }}"></script>
<script src="{{ asset('assets/pages/scripts/listado/workbench-disenador-preview.js') }}?v={{ file_exists($disenadorJs) ? filemtime($disenadorJs) : time() }}"></script>
<script src="{{ asset('assets/pages/scripts/listado/workbench-vista-guardar.js') }}?v={{ file_exists($vistaGuardarJs) ? filemtime($vistaGuardarJs) : time() }}"></script>
<script src="{{ asset('assets/pages/scripts/stock/articulo/workbench.js') }}?v={{ file_exists($articuloWorkbenchJs) ? filemtime($articuloWorkbenchJs) : time() }}"></script>
<script src="{{asset("assets/pages/scripts/configuracion/salida.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/configuracion/configurar_salida.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/configuracion/modeloetiqueta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/configuracion/configurar_modeloetiqueta.js")}}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/listaprecio/consulta.js') }}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/stock/articulo/consulta-precios.js")}}" type="text/javascript"></script>
@if (can('imprimir-articulos-qr', false))
<script src="{{ asset('assets/pages/scripts/stock/articulo/etiqueta-imprimiendo.js') }}" type="text/javascript"></script>
@if (\App\Support\Configuracion\EntornoEmpresaSupport::esFerli())
<script src="{{ asset('assets/pages/scripts/stock/articulo/etiqueta-ferli.js') }}" type="text/javascript"></script>
@else
<script src="{{ asset('assets/pages/scripts/stock/articulo/etiqueta-cantidad.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/articulo/etiqueta-npu.js') }}" type="text/javascript"></script>
@endif
@endif
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
        <div class="card card-info lw-workbench shadow-sm">
            <div class="card-header lw-header d-flex flex-wrap align-items-center justify-content-between">
                <h3 class="card-title mb-0">Art&iacute;culos</h3>
                @include('includes.configurar-salida')
                @include('includes.configurar-modeloetiqueta')
                <div class="card-tools ml-auto d-flex flex-wrap align-items-center justify-content-end" style="gap:.4rem;">
    				<a href="#" onclick="configurarSalida(); return false;" class="btn btn-outline-light btn-sm">
						<i class="fa fa-fw fa-cog"></i> Configura salida
					</a>
    				<a href="#" onclick="configurarModeloEtiqueta(); return false;" class="btn btn-success btn-sm">
						<i class="fa fa-fw fa-print"></i> Configura etiqueta
					</a>
                    @if (can('crear-articulos', false))
                        <a href="{{ route('crear_articulo', $retornoListadoQuery) }}" class="btn btn-light btn-sm">
                            <i class="fa fa-plus"></i> Nuevo art&iacute;culo
                        </a>
                    @endif
                </div>
            </div>
            <form method="get" action="{{ route('articulo') }}" id="form-filtros-articulo" class="mb-0">
                <input type="hidden" name="filtro_busqueda_rapida" id="filtro_busqueda_rapida" value="">
                <input type="hidden" name="filtro_modo" id="filtro_modo" value="{{ $filtros['modo'] ?? 'todos' }}">
                <input type="hidden" name="filtro_valor" id="filtro_valor" value="{{ $filtros['valor'] ?? '' }}">
                <input type="hidden" name="columnas" id="lw_columnas_csv" value="{{ implode(',', $columnasVisibles ?? []) }}">
                @if (($filtros['estado'] ?? ArticuloListadoFiltros::ESTADO_ACTIVO) === '')
                    <input type="hidden" name="filtro_estado" value="TODOS">
                @elseif (($filtros['estado'] ?? ArticuloListadoFiltros::ESTADO_ACTIVO) !== ArticuloListadoFiltros::ESTADO_ACTIVO)
                    <input type="hidden" name="filtro_estado" value="{{ $filtros['estado'] }}">
                @endif
                @if (ArticuloListadoFiltros::filtroCanalActivo() && ($filtros['canal'] ?? ArticuloListadoFiltros::CANAL_TODOS) !== ArticuloListadoFiltros::CANAL_TODOS)
                    <input type="hidden" name="filtro_canal" value="{{ $filtros['canal'] }}">
                @endif
                @if (($filtros['empresa_scope'] ?? '') === 'todas')
                    <input type="hidden" name="empresa_todas" value="1">
                @elseif (! empty($filtros['empresa_id']))
                    <input type="hidden" name="empresa_id" value="{{ $filtros['empresa_id'] }}">
                @endif
                @if ($vistaActiva ?? null)
                    <input type="hidden" name="vista_id" value="{{ $vistaActiva->id }}">
                @endif
                <div class="lw-toolbar">
                    <div class="lw-toolbar-left">
                        <select id="lw-vista-select" class="form-control form-control-sm lw-vista-select"
                                data-base-url="{{ route('articulo') }}"
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
                        @include('stock.articulo.partials.filtros_externos')
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
                        @if (ArticuloListadoFiltros::tieneCriteriosTexto($filtros ?? []))
                            <a href="{{ route('articulo') }}" class="btn btn-sm btn-outline-warning">
                                <i class="fa fa-eraser"></i> Limpiar
                            </a>
                        @endif
                    </div>
                </div>
                @include('stock.articulo.partials.workbench_qbe')
            </form>
            <div class="px-3 pt-2">
                @include('includes.listado.workbench_cortes', ['cortes' => $cortes ?? []])
            </div>
            <div class="card-body py-2 border-bottom bg-white d-flex flex-wrap align-items-center justify-content-between">
                <div class="mb-1 mb-md-0">
                    @include('includes.exportar-tabla-queryparams', [
                        'ruta' => 'lista_articulo',
                        'queryparams' => $filtrosQuery ?? [],
                    ])
                </div>
            </div>
            <div class="card-body table-responsive p-0">
                @php
                    $filtroEmpresaActivo = ArticuloListadoFiltros::filtroEmpresaActivo();
                    $tituloColArticulo = function (string $key, string $fallback) use ($etiquetasColumnas) {
                        return ($etiquetasColumnas ?? [])[$key] ?? $fallback;
                    };
                    $sortActual = $filtros['sort'][0]['campo'] ?? '';
                    $sortDir = $filtros['sort'][0]['dir'] ?? 'asc';
                    $urlOrdenArticulo = function (string $col) use ($filtrosQuery, $sortActual, $sortDir) {
                        $q = $filtrosQuery ?? [];
                        unset($q['sort'], $q['group']);
                        $q['sort'] = [[
                            'campo' => $col,
                            'dir' => ($sortActual === $col && $sortDir === 'asc') ? 'desc' : 'asc',
                        ]];

                        return route('articulo', $q);
                    };
                    $marcaOrdenArticulo = function (string $col) use ($sortActual, $sortDir) {
                        if ($sortActual !== $col) {
                            return '';
                        }

                        return $sortDir === 'asc' ? ' ↑' : ' ↓';
                    };
                @endphp
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            @foreach ($columnasVisibles as $keyColumna)
                            @include('stock.articulo.partials.workbench_th', ['key' => $keyColumna])
                            @endforeach
                            <th data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
						@foreach($articulos as $articulo)
    						<tr>
                                @foreach ($columnasVisibles as $keyColumna)
                                @include('stock.articulo.partials.workbench_celda', ['key' => $keyColumna])
                                @endforeach
                            <td>
                       			@if (can('editar-articulos', false))
                                	<a href="{{route('editar_articulo', ['id' => $articulo->id] + $retornoListadoQuery)}}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                        <i class="fa fa-edit"></i>
                                	</a>
								@endif
                       			@if (can('imprimir-articulos-qr', false))
          							@if (\App\Support\Configuracion\EntornoEmpresaSupport::esFerli())
                                	<button type="button"
                                	    class="btn-accion-tabla btn-imprimir-etiqueta-ferli tooltipsC"
                                	    title="Emitir etiquetas (combinación / talle)"
                                	    data-articulo-id="{{ $articulo->id }}"
                                	    data-articulo-sku="{{ $articulo->codigoarticulo ?? $articulo->sku ?? '' }}"
                                	    data-articulo-descripcion="{{ $articulo->descripcion ?? '' }}">
                                        <i class="fa fa-qrcode"></i>
                                	</button>
          							@elseif((string)($articulo->numeroparte ?? '0') === '1')
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
                                    @if (\App\Support\Stock\ArticuloKardexCombinacionSupport::uiActiva())
                                	<button type="button"
                                	    class="btn-accion-tabla btn-kardex-combinacion-ferli tooltipsC"
                                	    title="Kardex por combinación y depósito"
                                	    data-articulo-id="{{ $articulo->id }}"
                                	    data-articulo-sku="{{ $articulo->codigoarticulo ?? $articulo->sku ?? '' }}"
                                	    data-articulo-descripcion="{{ $articulo->descripcion ?? '' }}">
                                        <i class="fa fa-list-alt text-info"></i>
                                	</button>
                                    @else
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
                       			@if (can('listar-reporte-historial-precios-compra', false))
                                	<button type="button"
                                	    class="btn-accion-tabla btn-historial-precios-articulo tooltipsC"
                                	    title="Historial de precios de compra"
                                	    data-articulo-id="{{ $articulo->id }}">
                                        <i class="fa fa-chart-line text-success"></i>
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
@include('stock.articulo.partials.workbench_modales')
@include('includes.stock.modalconsultaprecioarticulo')
@include('includes.stock.modalconsultalistaprecio')
@if (can('imprimir-articulos-qr', false))
@if (\App\Support\Configuracion\EntornoEmpresaSupport::esFerli())
@include('includes.stock.modal_etiqueta_ferli')
@else
@include('includes.stock.modaletiquetanpuarticulo')
@include('includes.stock.modaletiquetacantidadarticulo')
@endif
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
@endsection
