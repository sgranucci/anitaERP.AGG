@extends("theme.$theme.layout")
@section('titulo')
Fórmulas de artículos
@endsection

@section("styles")
<link rel="stylesheet" href="{{ asset('assets/css/listado-workbench.css') }}?v={{ filemtime(public_path('assets/css/listado-workbench.css')) }}">
@endsection

@section("scripts")
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
@php
    $qbeGruposJs = public_path('assets/pages/scripts/listado/workbench-qbe-grupos.js');
    $ordenJs = public_path('assets/pages/scripts/listado/workbench-orden.js');
    $agruparJs = public_path('assets/pages/scripts/listado/workbench-agrupar.js');
    $disenadorJs = public_path('assets/pages/scripts/listado/workbench-disenador-preview.js');
    $vistaGuardarJs = public_path('assets/pages/scripts/listado/workbench-vista-guardar.js');
    $formulaJs = public_path('assets/pages/scripts/stock/formula_articulo/workbench.js');
@endphp
<script src="{{ asset('assets/pages/scripts/listado/workbench-qbe-grupos.js') }}?v={{ file_exists($qbeGruposJs) ? filemtime($qbeGruposJs) : time() }}"></script>
<script src="{{ asset('assets/pages/scripts/listado/workbench-orden.js') }}?v={{ file_exists($ordenJs) ? filemtime($ordenJs) : time() }}"></script>
<script src="{{ asset('assets/pages/scripts/listado/workbench-agrupar.js') }}?v={{ file_exists($agruparJs) ? filemtime($agruparJs) : time() }}"></script>
<script src="{{ asset('assets/pages/scripts/listado/workbench-disenador-preview.js') }}?v={{ file_exists($disenadorJs) ? filemtime($disenadorJs) : time() }}"></script>
<script src="{{ asset('assets/pages/scripts/listado/workbench-vista-guardar.js') }}?v={{ file_exists($vistaGuardarJs) ? filemtime($vistaGuardarJs) : time() }}"></script>
<script src="{{ asset('assets/pages/scripts/stock/formula_articulo/workbench.js') }}?v={{ file_exists($formulaJs) ? filemtime($formulaJs) : time() }}"></script>
@endsection

@php
    use App\Support\Stock\FormulaArticuloListadoFiltros;
    use Illuminate\Support\Facades\Schema;

    $limpiarQ = ['filtro_limpiar' => 1];
    if (($filtros['estado'] ?? '') !== '') {
        $limpiarQ['filtro_estado'] = $filtros['estado'];
    }
    if (($filtros['articulos'] ?? '') !== '') {
        $limpiarQ['filtro_articulos'] = $filtros['articulos'];
    }
    if (($filtros['con_opcionales'] ?? '') !== '') {
        $limpiarQ['con_opcionales'] = $filtros['con_opcionales'];
    }
    $limpiarUrl = route('consultar_formula_articulo', $limpiarQ);
    $camposOrdenablesThead = FormulaArticuloListadoFiltros::camposOrdenables();
    $ordenActualThead = \App\Support\Listado\ListadoOrdenamientoSupport::normalizar(
        $filtros['sort'] ?? [],
        $camposOrdenablesThead
    );
    $qsQuitarOrden = $filtrosQuery ?? [];
    unset($qsQuitarOrden['sort'], $qsQuitarOrden['page']);
    $qsQuitarOrden['quitar_orden'] = 1;
    $urlQuitarOrden = route('consultar_formula_articulo', $qsQuitarOrden);
    $indexTieneRanura = config('app.empresa') === 'FRASLE' && Schema::hasColumn('formula_articulo_hijo', 'ranura');
    $indexTienePermitido = config('app.empresa') === 'EL BIERZO' && Schema::hasColumn('formula_articulo_hijo', 'permitido');
    $indexGastOpc = ! empty($opcionalesHabilitados);
@endphp

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        @include('includes.proceso_overlay_aviso', [
            'overlayId' => 'formula-export-overlay',
            'tituloId' => 'formula-export-titulo',
            'subtituloId' => 'formula-export-subtitulo',
            'titulo' => 'Exportando…',
            'subtitulo' => 'Generando el archivo. No cierre la página.',
        ])
        @if (! empty($sinFormulasCargadas ?? false))
        <div class="alert alert-info">
            No hay fórmulas en el ERP. Cree registros con <strong>Nueva fórmula</strong> o cargue los datos según el procedimiento definido para esta instalación.
        </div>
        @endif
        <div class="card card-info lw-workbench shadow-sm">
            <div class="card-header lw-header d-flex flex-wrap align-items-center justify-content-between">
                <h3 class="card-title mb-0">Fórmulas de artículos</h3>
                <div class="card-tools ml-auto d-flex flex-wrap align-items-center justify-content-end" style="gap:.4rem;">
                    @include('includes.stock.boton-manual-stock-gastronomia')
                    @if (can('crear-formula-articulo', false))
                    <a href="{{ route('crear_formula_articulo') }}" class="btn btn-light btn-sm">
                        <i class="fa fa-plus"></i> Nueva fórmula
                    </a>
                    @endif
                    @if (can('actualizar-formula-articulo', false))
                    <form action="{{ route('vincular_formula_articulo_por_codigo') }}" method="POST" class="d-inline" onsubmit="return confirm('Vincular cada fórmula con el artículo cuyo SKU coincide (código Anita → V0000, ej. 365 → V0365) y actualizar articulo.formula.\n\n¿Continuar?');">
                        @csrf
                        <button type="submit" class="btn btn-outline-light btn-sm" title="Vincular fórmulas con artículos por código → SKU V####">
                            <i class="fa fa-fw fa-link"></i> Vincular con artículos
                        </button>
                    </form>
                    @endif
                </div>
            </div>
            @php
                $cantTotalListado = method_exists($formulas, 'total') ? (int) $formulas->total() : $formulas->count();
            @endphp
            <div class="px-3 py-1 border-bottom bg-white d-flex flex-wrap align-items-baseline" style="gap:.25rem .6rem;font-size:.85rem;">
                <span class="text-muted">Listado</span>
                <strong>{{ number_format($cantTotalListado, 0, ',', '.') }}</strong>
                <span class="text-muted">{{ $cantTotalListado === 1 ? 'fórmula' : 'fórmulas' }}</span>
            </div>
            @if (! ($workbenchListo ?? false))
                <div class="alert alert-warning lw-aviso-migracion mb-0">
                    <strong>Migración pendiente.</strong>
                    Para vistas y configuración de grilla hace falta la tabla <code>listado_vista</code>.
                </div>
            @endif
            <form method="get" action="{{ route('consultar_formula_articulo') }}" id="form-filtros-formula-articulo" class="mb-0">
                <input type="hidden" name="filtro_busqueda_rapida" id="filtro_busqueda_rapida" value="">
                <input type="hidden" name="filtro_modo" id="filtro_modo" value="{{ $filtros['modo'] ?? 'todos' }}">
                <input type="hidden" name="filtro_valor" id="filtro_valor" value="{{ $filtros['valor'] ?? '' }}">
                <input type="hidden" name="columnas" id="lw_columnas_csv" value="{{ implode(',', $columnasVisibles ?? []) }}">
                @if (($filtros['estado'] ?? '') !== '')
                    <input type="hidden" name="filtro_estado" value="{{ $filtros['estado'] }}">
                @endif
                @if (($filtros['articulos'] ?? '') !== '')
                    <input type="hidden" name="filtro_articulos" value="{{ $filtros['articulos'] }}">
                @endif
                @if (($filtros['con_opcionales'] ?? '') !== '')
                    <input type="hidden" name="con_opcionales" value="{{ $filtros['con_opcionales'] }}">
                @endif
                @if ($vistaActiva ?? null)
                    <input type="hidden" name="vista_id" value="{{ $vistaActiva->id }}">
                @elseif (! empty($filtrosQuery['vista_estandar']))
                    <input type="hidden" name="vista_estandar" value="1">
                @endif
                <div class="lw-toolbar">
                    <div class="lw-toolbar-left">
                        <select id="lw-vista-select" class="form-control form-control-sm lw-vista-select"
                                data-base-url="{{ route('consultar_formula_articulo') }}"
                                title="Vistas guardadas"
                                @if (! ($workbenchListo ?? false)) disabled @endif>
                            <option value="">Vista estándar</option>
                            @foreach (($vistasListado ?? []) as $vista)
                                <option value="{{ $vista->id }}" @if (($vistaActiva ?? null) && (int) $vistaActiva->id === (int) $vista->id) selected @endif>
                                    {{ $vista->nombre }}
                                    @if ($vista->es_default) ★ @endif
                                    @if ($vista->compartida) (compartida) @endif
                                </option>
                            @endforeach
                        </select>
                        <button type="button" class="btn btn-sm btn-primary" data-toggle="modal" data-target="#modal-lw-grilla"
                                @if (! ($workbenchListo ?? false)) disabled title="Requiere migración" @endif>
                            <i class="fa fa-th"></i> Diseñar vista
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-info collapsed" data-toggle="collapse" data-target="#lw-qbe-panel" aria-expanded="false" aria-controls="lw-qbe-panel">
                            <i class="fa fa-filter"></i> QBE
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-toggle="modal" data-target="#modal-lw-etiquetas"
                                @if (! ($workbenchListo ?? false)) disabled title="Requiere migración" @endif>
                            <i class="fa fa-font"></i> Defaults instalación
                        </button>
                    </div>
                    <div class="lw-toolbar-right">
                        <input type="search" id="lw-search-rapida" class="form-control form-control-sm lw-search-rapida"
                               value="{{ ($filtros['modo'] ?? '') !== 'qbe' ? ($filtros['valor'] ?? '') : '' }}"
                               placeholder="Número, SKU o artículo…"
                               autocomplete="off">
                        <button type="button" id="btn-lw-buscar-rapida" class="btn btn-sm btn-primary">
                            <i class="fa fa-search"></i>
                        </button>
                        @if (FormulaArticuloListadoFiltros::tieneCriteriosTexto($filtros ?? []))
                            <a href="{{ $limpiarUrl }}" class="btn btn-sm btn-outline-warning">
                                <i class="fa fa-eraser"></i> Limpiar
                            </a>
                        @endif
                        @if ($ordenActualThead !== [])
                            <a href="{{ $urlQuitarOrden }}" class="btn btn-sm btn-outline-secondary" title="Vuelve al orden de instalación y lo saca de la vista">
                                <i class="fa fa-sort"></i> Quitar orden
                            </a>
                        @endif
                    </div>
                </div>
                @include('stock.formula_articulo.partials.filtros_externos')
                @include('stock.formula_articulo.partials.workbench_qbe')
            </form>
            <div class="px-3 pt-2">
                @include('includes.listado.workbench_cortes', ['cortes' => $cortes ?? []])
            </div>
            <div class="card-body py-2 border-bottom bg-white">
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'listar_formula_articulo',
                    'queryparams' => $filtrosQuery ?? [],
                ])
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead style="background-color:#85C1E9;color:#17202A;">
                        <tr>
                            @foreach ($columnasVisibles as $keyColumna)
                                @include('stock.formula_articulo.partials.workbench_th', ['key' => $keyColumna])
                            @endforeach
                            <th class="text-nowrap" style="width:70px"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $agruparActivo = \App\Support\Listado\ListadoAgrupacionSupport::normalizar(
                                $filtros['agrupar'] ?? [],
                                FormulaArticuloListadoFiltros::camposOrdenables()
                            );
                            $filasVista = \App\Support\Listado\ListadoAgrupacionSupport::segmentar(
                                $formulas,
                                $agruparActivo,
                                static fn ($row, string $campo): string => \App\Support\Stock\FormulaArticuloListadoColumnas::valorCelda($row, $campo),
                                $etiquetasColumnas ?? [],
                                ! empty($cortes['por_clave']) ? $cortes['por_clave'] : null
                            );
                            $colspanGrilla = count($columnasVisibles) + 1;
                        @endphp
                        @forelse ($filasVista as $filaVista)
                            @if (($filaVista['type'] ?? '') === 'header')
                                <tr class="lw-group-header lw-group-nivel-{{ (int) ($filaVista['nivel'] ?? 0) }}">
                                    <td colspan="{{ $colspanGrilla }}">
                                        <i class="fa fa-folder-open-o"></i>
                                        <strong>{{ $filaVista['label'] }}:</strong>
                                        {{ $filaVista['valor'] !== '' ? $filaVista['valor'] : '(vacío)' }}
                                        <span class="lw-group-count">
                                            {{ number_format((int) ($filaVista['count'] ?? 0), 0, ',', '.') }}
                                        </span>
                                    </td>
                                </tr>
                            @else
                                @php $data = $filaVista['row']; @endphp
                                <tr>
                                    @foreach ($columnasVisibles as $keyColumna)
                                        @include('stock.formula_articulo.partials.workbench_celda', [
                                            'key' => $keyColumna,
                                            'data' => $data,
                                            'columnasVisibles' => $columnasVisibles,
                                            'indexGastOpc' => $indexGastOpc,
                                            'indexTieneRanura' => $indexTieneRanura,
                                            'indexTienePermitido' => $indexTienePermitido,
                                        ])
                                    @endforeach
                                    <td class="text-nowrap align-middle">
                                        @if (can('editar-formula-articulo', false))
                                        <a href="{{ route('editar_formula_articulo', ['id' => $data->id]) }}" class="btn-accion-tabla tooltipsC" title="Editar">
                                            <i class="fa fa-edit"></i>
                                        </a>
                                        @endif
                                        @if (can('borrar-formula-articulo', false))
                                        <button type="button" class="btn-accion-tabla eliminar-formula tooltipsC text-danger" title="Eliminar" data-url="{{ route('eliminar_formula_articulo', ['id' => $data->id]) }}">
                                            <i class="fa fa-trash"></i>
                                        </button>
                                        @endif
                                    </td>
                                </tr>
                            @endif
                        @empty
                            <tr>
                                <td colspan="{{ count($columnasVisibles) + 1 }}" class="text-center text-muted py-4">No hay fórmulas con estos filtros.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-3 py-2">
                {{ $formulas->appends($filtrosQuery ?? [])->links() }}
            </div>
        </div>
    </div>
</div>
@include('stock.formula_articulo.partials.workbench_modales')
@endsection
