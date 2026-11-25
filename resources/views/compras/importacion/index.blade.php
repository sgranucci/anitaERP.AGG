@extends("theme.$theme.layout")
@section('titulo')
Importaciones
@endsection

@section("styles")
<link rel="stylesheet" href="{{ asset('assets/css/listado-workbench.css') }}?v={{ filemtime(public_path('assets/css/listado-workbench.css')) }}">
<link rel="stylesheet" href="{{ asset('assets/css/tabla-ancha-reporte.css') }}?v={{ @filemtime(public_path('assets/css/tabla-ancha-reporte.css')) ?: time() }}">
<style>
    /* 2 botones (editar/eliminar). Sticky solo si hay scroll. */
    .lw-workbench .imp-grilla {
        --tabla-ancha-der: 5.5rem;
    }
    .lw-workbench .imp-grilla th.lw-col-acciones,
    .lw-workbench .imp-grilla td.lw-col-acciones {
        width: var(--tabla-ancha-der) !important;
        min-width: var(--tabla-ancha-der) !important;
        max-width: var(--tabla-ancha-der) !important;
        padding-left: 0.1rem;
        padding-right: 0.1rem;
    }
    .lw-workbench .imp-grilla .lw-table-fit .tabla-ancha {
        width: 100% !important;
        min-width: 100% !important;
        max-width: 100%;
    }
    .lw-workbench .imp-grilla .lw-acciones-inner .btn-accion-tabla {
        min-width: 1.45rem;
        padding: 0 0.05rem;
    }
    .lw-workbench .imp-grilla .lw-acciones-inner form {
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
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
@php
    $qbeGruposJs = public_path('assets/pages/scripts/listado/workbench-qbe-grupos.js');
    $ordenJs = public_path('assets/pages/scripts/listado/workbench-orden.js');
    $agruparJs = public_path('assets/pages/scripts/listado/workbench-agrupar.js');
    $disenadorJs = public_path('assets/pages/scripts/listado/workbench-disenador-preview.js');
    $vistaGuardarJs = public_path('assets/pages/scripts/listado/workbench-vista-guardar.js');
    $importacionWorkbenchJs = public_path('assets/pages/scripts/compras/importacion/workbench.js');
    $columnasDndJs = public_path('assets/pages/scripts/listado/workbench-columnas-dnd.js');
    $tablaAnchaJs = public_path('assets/pages/scripts/admin/tabla-ancha-reporte.js');
@endphp
<script src="{{ asset('assets/pages/scripts/listado/workbench-qbe-grupos.js') }}?v={{ file_exists($qbeGruposJs) ? filemtime($qbeGruposJs) : time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/listado/workbench-orden.js') }}?v={{ file_exists($ordenJs) ? filemtime($ordenJs) : time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/listado/workbench-agrupar.js') }}?v={{ file_exists($agruparJs) ? filemtime($agruparJs) : time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/listado/workbench-disenador-preview.js') }}?v={{ file_exists($disenadorJs) ? filemtime($disenadorJs) : time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/listado/workbench-vista-guardar.js') }}?v={{ file_exists($vistaGuardarJs) ? filemtime($vistaGuardarJs) : time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/importacion/workbench.js') }}?v={{ file_exists($importacionWorkbenchJs) ? filemtime($importacionWorkbenchJs) : time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/listado/workbench-columnas-dnd.js') }}?v={{ file_exists($columnasDndJs) ? filemtime($columnasDndJs) : time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/admin/tabla-ancha-reporte.js') }}?v={{ file_exists($tablaAnchaJs) ? filemtime($tablaAnchaJs) : time() }}"></script>
@endsection

@php
    use App\Support\Compras\ImportacionEstados;
    use App\Support\Compras\ImportacionListadoFiltros;
    use App\Support\Compras\ImportacionListadoColumnas;

    $retornoListadoQuery = \App\Support\Listado\QueryRetornoListado::retornoLinksDesdeFiltrosQuery($filtrosQuery ?? []);
    $limpiarUrl = route('importacion', ['limpiar_filtros' => 1]);
    $columnasVisibles = $columnasVisibles ?? ImportacionListadoColumnas::defaultsVisibles();
    $catalogoColumnas = $catalogoColumnas ?? ImportacionListadoColumnas::catalogoActivo();
    $etiquetasColumnas = $etiquetasColumnas ?? [];
    $tieneQbe = ImportacionListadoFiltros::tieneCriteriosAplicados($filtros ?? []);
    $vistasListado = $vistasListado ?? collect();
    $vistaActiva = $vistaActiva ?? null;
    $workbenchListo = $workbenchListo ?? false;
    $camposOrdenablesTheadBarra = \App\Support\Compras\ImportacionListadoFiltros::camposOrdenables();
    $ordenActualThead = \App\Support\Listado\ListadoOrdenamientoSupport::normalizar(
        $filtros['orden'] ?? [],
        $camposOrdenablesTheadBarra
    );
    $qsQuitarOrden = $filtrosQuery ?? [];
    unset($qsQuitarOrden['sort'], $qsQuitarOrden['page']);
    $qsQuitarOrden['quitar_orden'] = 1;
    $urlQuitarOrden = route('importacion', $qsQuitarOrden);

    $estadoAct = (string) ($filtros['filtro_estado'] ?? '');
@endphp

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')

        <div class="card card-info lw-workbench shadow-sm">
            <div class="card-header lw-header d-flex flex-wrap align-items-center justify-content-between">
                <h3 class="card-title mb-0">
                    <i class="fa fa-ship mr-1"></i> Importaciones
                    <small class="ml-2" style="opacity:.85;font-weight:400;">Workbench · consulta multi-campo</small>
                </h3>
                <div class="card-tools ml-auto d-flex flex-wrap align-items-center justify-content-end" style="gap:.4rem;">
                    <a href="{{ route('manual_importacion') }}" class="btn btn-outline-light btn-sm" target="_blank" rel="noopener" title="Manual del módulo">
                        <i class="fas fa-book-open"></i> Manual
                    </a>
                    <a href="{{ route('importacion_cashflow') }}" class="btn btn-outline-light btn-sm">
                        <i class="fa fa-line-chart"></i> Cashflow
                    </a>
                    @if (can('crear-importacion', false))
                        <a href="{{ route('crear_importacion', $retornoListadoQuery) }}" class="btn btn-light btn-sm">
                            <i class="fa fa-plus"></i> Nueva importación
                        </a>
                    @endif
                </div>
            </div>

            @if (! $workbenchListo)
                <div class="alert alert-warning lw-aviso-migracion mb-0">
                    <strong>Migración pendiente.</strong>
                    Para vistas y configuración de grilla hace falta la tabla <code>listado_vista</code>.
                </div>
            @endif

            <form method="get" action="{{ route('importacion') }}" id="form-filtros-importacion" class="mb-0">
                <input type="hidden" name="filtro_busqueda_rapida" id="filtro_busqueda_rapida" value="">
                <input type="hidden" name="filtro_modo" id="filtro_modo" value="{{ $filtros['modo'] ?? 'todos' }}">
                <input type="hidden" name="filtro_valor" id="filtro_valor" value="{{ $filtros['valor'] ?? '' }}">
                <input type="hidden" name="columnas" id="lw_columnas_csv" value="{{ implode(',', $columnasVisibles) }}">
                @if ($vistaActiva)
                    <input type="hidden" name="vista_id" value="{{ $vistaActiva->id }}">
                @endif
                @if ($estadoAct !== '')
                    <input type="hidden" name="filtro_estado" value="{{ $estadoAct }}">
                @endif
                @if (! empty($filtros['empresa_todas']))
                    <input type="hidden" name="empresa_todas" value="1">
                @elseif (! empty($filtros['empresa_id']))
                    <input type="hidden" name="empresa_id" value="{{ $filtros['empresa_id'] }}">
                @endif

                <div class="lw-toolbar">
                    <div class="lw-toolbar-left">
                        <select id="lw-vista-select" class="form-control form-control-sm lw-vista-select"
                                data-base-url="{{ route('importacion') }}"
                                title="Vistas guardadas"
                                @if (! $workbenchListo) disabled @endif>
                            <option value="">Vista estándar</option>
                            @foreach ($vistasListado as $vista)
                                <option value="{{ $vista->id }}" @if ($vistaActiva && (int) $vistaActiva->id === (int) $vista->id) selected @endif>
                                    {{ $vista->nombre }}
                                    @if ($vista->es_default) ★ @endif
                                    @if ($vista->compartida) (compartida) @endif
                                </option>
                            @endforeach
                        </select>
                        <button type="button" class="btn btn-sm btn-primary" data-toggle="modal" data-target="#modal-lw-grilla"
                                @if (! $workbenchListo) disabled title="Requiere migración" @endif>
                            <i class="fa fa-th"></i> Diseñar vista
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-info collapsed" data-toggle="collapse" data-target="#lw-qbe-panel" aria-expanded="false" aria-controls="lw-qbe-panel">
                            <i class="fa fa-filter"></i> QBE
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-toggle="modal" data-target="#modal-lw-etiquetas"
                                @if (! $workbenchListo) disabled title="Requiere migración" @endif>
                            <i class="fa fa-font"></i> Defaults instalación
                        </button>
                    </div>
                    <div class="lw-toolbar-right">
                        <input type="search" id="lw-search-rapida" class="form-control form-control-sm lw-search-rapida"
                               value="{{ ($filtros['modo'] ?? '') !== 'qbe' ? ($filtros['valor'] ?? '') : '' }}"
                               placeholder="Código, proveedor… (Enter)"
                               autocomplete="off">
                        <button type="button" id="btn-lw-buscar-rapida" class="btn btn-sm btn-primary">
                            <i class="fa fa-search"></i>
                        </button>
                        @if ($vistaActiva && $tieneQbe && ($filtros['modo'] ?? '') === 'qbe')
                            <button type="submit" name="quitar_qbe" value="1" class="btn btn-sm btn-outline-warning"
                                    title="Saca los filtros guardados en esta vista. Las columnas no cambian.">
                                <i class="fa fa-eraser"></i> Quitar filtros
                            </button>
                        @elseif ($tieneQbe || (($filtros['valor'] ?? '') !== ''))
                            <a href="{{ $limpiarUrl }}" class="btn btn-sm btn-outline-warning">
                                <i class="fa fa-eraser"></i> Limpiar filtros
                            </a>
                        @endif
                        @if (($ordenActualThead ?? []) !== [])
                            <a href="{{ $urlQuitarOrden }}" class="btn btn-sm btn-outline-secondary" title="Vuelve al orden de instalación y lo saca de la vista">
                                <i class="fa fa-undo"></i> Orden estándar
                            </a>
                        @endif

                    </div>
                </div>

                @include('compras.importacion.partials.workbench_qbe')

                @if ($tieneQbe)
                    <div class="lw-chips">
                        @if (($filtros['modo'] ?? '') === 'qbe')
                            @php
                                $qbeChip = \App\Support\Listado\ListadoQbeSupport::paraUi($filtros['qbe'] ?? []);
                                $opsChip = array_merge(
                                    ImportacionListadoFiltros::OPERADORES_TEXTO,
                                    ImportacionListadoFiltros::OPERADORES_ENTERO,
                                    ImportacionListadoFiltros::OPERADORES_BOOLEANO,
                                    ImportacionListadoFiltros::OPERADORES_FECHA,
                                    ImportacionListadoFiltros::OPERADORES_DECIMAL
                                );
                            @endphp
                            @foreach ($qbeChip['grupos'] as $gi => $grupo)
                                @if ($gi > 0)
                                    <span class="lw-chip lw-chip-logic">{{ ($qbeChip['entre_grupos'] ?? 'and') === 'or' ? 'O' : 'Y' }}</span>
                                @endif
                                @foreach (($grupo['criterios'] ?? []) as $c)
                                    @if (($c['op'] ?? '') === 'vacio' || ($c['op'] ?? '') === 'entre' || trim((string) ($c['valor'] ?? '')) !== '')
                                        <span class="lw-chip">
                                            <strong>{{ $etiquetasColumnas[$c['campo'] ?? ''] ?? ($c['campo'] ?? '') }}</strong>
                                            {{ $opsChip[$c['op'] ?? 'contiene'] ?? ($c['op'] ?? 'contiene') }}
                                            @if (($c['op'] ?? '') === 'entre')
                                                «{{ $c['valor'] ?? '' }}»…«{{ $c['valor_hasta'] ?? '' }}»
                                            @elseif (($c['op'] ?? '') !== 'vacio')
                                                «{{ $c['valor'] ?? '' }}»
                                            @endif
                                        </span>
                                    @endif
                                @endforeach
                            @endforeach
                        @elseif (($filtros['valor'] ?? '') !== '')
                            <span class="lw-chip"><strong>Texto</strong> {{ $filtros['valor'] }}</span>
                        @endif
                    </div>
                @endif

                {{-- Chips externos (AND) + export compacto a la derecha --}}
                <div class="px-3 pb-2">
                    <div class="d-flex flex-wrap align-items-center mb-1" style="gap:.25rem;">
                        <span class="mr-1 text-muted small">Estado:</span>
                        <a href="{{ route('importacion', array_merge($filtrosQuery ?? [], ['filtro_estado' => null])) }}"
                           class="btn btn-sm {{ $estadoAct === '' ? 'btn-primary' : 'btn-outline-secondary' }}">Todos</a>
                        @foreach ($estados as $est)
                            <a href="{{ route('importacion', array_merge($filtrosQuery ?? [], ['filtro_estado' => $est['id']])) }}"
                               class="btn btn-sm {{ $estadoAct === $est['id'] ? 'btn-primary' : 'btn-outline-secondary' }}">
                                {{ $est['nombre'] }}
                            </a>
                        @endforeach
                        <span class="ml-auto">
                            @include('includes.exportar-tabla-queryparams', [
                                'ruta' => 'lista_importacion',
                                'queryparams' => $filtrosQuery ?? [],
                                'variant' => 'compact',
                            ])
                        </span>
                    </div>
                    @if (($empresa_query ?? collect())->count() > 1)
                        <div class="d-flex flex-wrap align-items-center" style="gap:.25rem;">
                            <span class="mr-1 text-muted small">Empresa:</span>
                            <a href="{{ route('importacion', array_merge($filtrosQuery ?? [], ['empresa_todas' => 1, 'empresa_id' => null])) }}"
                               class="btn btn-sm {{ !empty($filtros['empresa_todas']) || empty($filtros['empresa_id']) ? 'btn-info' : 'btn-outline-secondary' }}">
                                Todas mis empresas
                            </a>
                            @foreach ($empresa_query as $emp)
                                <a href="{{ route('importacion', array_merge($filtrosQuery ?? [], ['empresa_id' => $emp->id, 'empresa_todas' => null])) }}"
                                   class="btn btn-sm {{ (int)($filtros['empresa_id'] ?? 0) === (int)$emp->id && empty($filtros['empresa_todas']) ? 'btn-info' : 'btn-outline-secondary' }}">
                                    {{ $emp->nombre }}
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>
            </form>

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
                $usaScrollHorizontal = LwGrilla::debeUsarScrollHorizontal($grillaLayout ?? []);
                $anchoAcciones = 88; // ~5.5rem: editar + eliminar
                $pctDatosDisponible = $usaScrollHorizontal
                    ? 100.0
                    : round(100 * $sumaAnchosPantalla / ($sumaAnchosPantalla + $anchoAcciones), 2);
                $camposOrdenablesThead = ImportacionListadoFiltros::camposOrdenables();
                $ordenActualThead = \App\Support\Listado\ListadoOrdenamientoSupport::normalizar(
                    $filtros['orden'] ?? [],
                    $camposOrdenablesThead
                );
            @endphp
            <div class="px-3 pt-2">
                @include('includes.listado.workbench_cortes', ['cortes' => $cortes ?? []])
            </div>
            <div class="tabla-ancha-grilla imp-grilla" data-tabla-ancha>
                <div class="tabla-ancha-scroll-top" hidden>
                    <div class="tabla-ancha-scroll-top-inner"></div>
                </div>
                <div class="tabla-ancha-wrap card-body p-0 lw-table-wrap {{ $usaScrollHorizontal ? 'table-responsive lw-table-scroll' : 'lw-table-fit' }}">
                <table class="tabla-ancha table table-striped table-bordered table-hover mb-0 lw-tabla-grilla" id="tabla-paginada">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            @foreach ($columnasVisibles as $key)
                                @php
                                    $cfg = $layoutPorKey[$key] ?? null;
                                    $cfgPantalla = $layoutPantallaPorKey[$key] ?? $cfg;
                                    $alinea = $cfg['alinea'] ?? 'izquierda';
                                    $ancho = (int) ($cfgPantalla['ancho'] ?? 100);
                                    $cls = LwGrilla::claseAlineacion($alinea);
                                    $tituloCol = $etiquetasColumnas[$key] ?? ($catalogoColumnas[$key]['label'] ?? $key);
                                    $minLegible = LwGrilla::anchoMinimoLegible($key, $catalogoColumnas[$key] ?? [], $tituloCol);
                                    if ($usaScrollHorizontal) {
                                        $styleCol = 'width:'.$ancho.'px;min-width:'.$minLegible.'px;';
                                    } else {
                                        // Sin piso de título: evita que la suma de mins empuje Acciones afuera.
                                        $pct = round(($ancho / $sumaAnchosPantalla) * $pctDatosDisponible, 2);
                                        $styleCol = 'width:'.$pct.'%;min-width:0;';
                                    }
                                    $esOrdenable = isset($camposOrdenablesThead[$key]);
                                    $dirCol = \App\Support\Listado\ListadoOrdenamientoSupport::direccionDeCampo($ordenActualThead, $key);
                                    $idxCol = \App\Support\Listado\ListadoOrdenamientoSupport::indiceDeCampo($ordenActualThead, $key);
                                    $qsSort = $filtrosQuery ?? [];
                                    unset($qsSort['sort']);
                                    if ($esOrdenable) {
                                        $ordenToggle = \App\Support\Listado\ListadoOrdenamientoSupport::togglePrimario(
                                            $ordenActualThead,
                                            $key,
                                            $camposOrdenablesThead
                                        );
                                        $qsSort = array_merge(
                                            $qsSort,
                                            \App\Support\Listado\ListadoOrdenamientoSupport::paraQueryString($ordenToggle)
                                        );
                                    }
                                @endphp
                                <th class="{{ $cls }} lw-col lw-th-drag {{ $esOrdenable ? 'lw-col-sortable' : '' }} {{ $dirCol ? 'lw-col-sorted' : '' }}"
                                    style="{{ $styleCol }}"
                                    title="{{ $tituloCol }} — arrastrá para mover · × para ocultar (con confirmación){{ $esOrdenable ? ' · clic para ordenar filas' : '' }}"
                                    data-col-key="{{ $key }}"
                                    draggable="true">
                                    <span class="lw-th-drag-handle" title="Arrastrar columna" aria-hidden="true"><i class="fa fa-grip-vertical"></i></span>
                                    @if ($esOrdenable)
                                        <a href="{{ route('importacion', $qsSort) }}" class="lw-sort-link" draggable="false">
                                            <span class="lw-th-label">{{ $tituloCol }}</span>
                                            @if ($dirCol === 'asc')
                                                <i class="fa fa-sort-up lw-sort-icon"></i>
                                            @elseif ($dirCol === 'desc')
                                                <i class="fa fa-sort-down lw-sort-icon"></i>
                                            @else
                                                <i class="fa fa-sort lw-sort-icon lw-sort-muted"></i>
                                            @endif
                                            @if ($idxCol !== null && count($ordenActualThead) > 1)
                                                <sup class="lw-sort-prio">{{ $idxCol + 1 }}</sup>
                                            @endif
                                        </a>
                                    @else
                                        <span class="lw-th-titulo">{{ $tituloCol }}</span>
                                    @endif
                                    <button type="button" class="lw-th-hide" title="Ocultar columna (pide confirmación)" aria-label="Ocultar columna" draggable="false">
                                        <i class="fa fa-times" aria-hidden="true"></i>
                                    </button>
                                </th>
                            @endforeach
                            <th class="lw-col-acciones" data-orderable="false"
                                style="width:{{ $anchoAcciones }}px;min-width:{{ $anchoAcciones }}px;max-width:{{ $anchoAcciones }}px;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $agruparActivo = \App\Support\Listado\ListadoAgrupacionSupport::normalizar(
                                $filtros['agrupar'] ?? [],
                                $camposOrdenablesThead
                            );
                            $filasVista = \App\Support\Listado\ListadoAgrupacionSupport::segmentar(
                                $datas,
                                $agruparActivo,
                                static fn ($row, string $campo): string => ImportacionListadoColumnas::valorCelda($row, $campo),
                                $etiquetasColumnas,
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
                                        {{ $filaVista['valor'] }}
                                        <span class="lw-group-count">
                                            {{ number_format((int) ($filaVista['count'] ?? 0), 0, ',', '.') }}
                                        </span>
                                    </td>
                                </tr>
                            @else
                                @php $data = $filaVista['row']; @endphp
                                <tr>
                                    @foreach ($columnasVisibles as $key)
                                        @include('compras.importacion.partials.workbench_celda', [
                                            'key' => $key,
                                            'data' => $data,
                                            'cfg' => $layoutPorKey[$key] ?? null,
                                            'retornoListadoQuery' => $retornoListadoQuery,
                                        ])
                                    @endforeach
                                    <td class="lw-col-acciones text-nowrap"
                                        style="width:{{ $anchoAcciones }}px;min-width:{{ $anchoAcciones }}px;max-width:{{ $anchoAcciones }}px;">
                                        <div class="lw-acciones-inner">
                                            @if (can('editar-importacion', false))
                                                <a href="{{ route('editar_importacion', ['id' => $data->id] + $retornoListadoQuery) }}" class="btn-accion-tabla tooltipsC" title="Editar">
                                                    <i class="fa fa-edit"></i>
                                                </a>
                                            @endif
                                            @if (can('eliminar-importacion', false))
                                                <form action="{{ route('eliminar_importacion', ['id' => $data->id]) }}" class="d-inline-flex form-eliminar mb-0" method="POST">
                                                    @csrf @method("delete")
                                                    <button type="submit" class="btn-accion-tabla eliminar tooltipsC" title="Eliminar">
                                                        <i class="fa fa-times-circle text-danger"></i>
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endif
                        @empty
                            <tr>
                                <td colspan="{{ count($columnasVisibles) + 1 }}">
                                    <div class="lw-empty">
                                        <div><i class="fa fa-search"></i></div>
                                        <div>Sin resultados con esos criterios.</div>
                                        <div class="small mt-1">Probá ampliar el QBE o usar la búsqueda rápida.</div>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                </div>
            </div>
        </div>
    </div>
</div>
{{ $datas->appends($filtrosQuery ?? [])->links() }}

{{-- Reordenar/ocultar columnas en thead; agregar = Diseñar vista. --}}
@include('includes.listado.workbench_columnas_dnd', [
    'rutaGuardarColumnasDnd' => 'guardar_columnas_listado_importacion',
    'paraQueryStringDnd' => [\App\Support\Compras\ImportacionListadoFiltros::class, 'paraQueryString'],
    'filtrosDnd' => $filtros ?? [],
])
@include('compras.importacion.partials.workbench_modales')
@endsection
