@extends("theme.$theme.layout")
@section('titulo')
Clientes
@endsection

@section("styles")
<link rel="stylesheet" href="{{ asset('assets/css/listado-workbench.css') }}?v={{ filemtime(public_path('assets/css/listado-workbench.css')) }}">
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>
@php
    $clienteWorkbenchJs = public_path('assets/pages/scripts/ventas/cliente/workbench.js');
    $qbeGruposJs = public_path('assets/pages/scripts/listado/workbench-qbe-grupos.js');
    $ordenJs = public_path('assets/pages/scripts/listado/workbench-orden.js');
    $agruparJs = public_path('assets/pages/scripts/listado/workbench-agrupar.js');
    $disenadorJs = public_path('assets/pages/scripts/listado/workbench-disenador-preview.js');
    $vistaGuardarJs = public_path('assets/pages/scripts/listado/workbench-vista-guardar.js');
@endphp
<script src="{{ asset('assets/pages/scripts/listado/workbench-qbe-grupos.js') }}?v={{ file_exists($qbeGruposJs) ? filemtime($qbeGruposJs) : time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/listado/workbench-orden.js') }}?v={{ file_exists($ordenJs) ? filemtime($ordenJs) : time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/listado/workbench-agrupar.js') }}?v={{ file_exists($agruparJs) ? filemtime($agruparJs) : time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/listado/workbench-disenador-preview.js') }}?v={{ file_exists($disenadorJs) ? filemtime($disenadorJs) : time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/listado/workbench-vista-guardar.js') }}?v={{ file_exists($vistaGuardarJs) ? filemtime($vistaGuardarJs) : time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/ventas/cliente/workbench.js') }}?v={{ file_exists($clienteWorkbenchJs) ? filemtime($clienteWorkbenchJs) : time() }}" type="text/javascript"></script>
@endsection

@php
    use App\Helpers\biblioteca;
    use App\Support\Ventas\ClienteListadoFiltros;
    use App\Support\Ventas\ClienteListadoColumnas;
    use App\Support\Configuracion\EntornoEmpresaSupport;

    $retornoListadoQuery = \App\Support\Listado\QueryRetornoListado::retornoLinksDesdeFiltrosQuery($filtrosQuery ?? []);
    $limpiarUrl = route('cliente');
    $esBierzo = EntornoEmpresaSupport::esElBierzo();
    $filtroCodigo = trim((string) ($filtros['codigo'] ?? ''));
    $columnasVisibles = $columnasVisibles ?? ClienteListadoColumnas::defaultsVisibles();
    $catalogoColumnas = $catalogoColumnas ?? ClienteListadoColumnas::catalogoActivo();
    $etiquetasColumnas = $etiquetasColumnas ?? [];
    $qbe = (array) ($filtros['qbe'] ?? []);
    $tieneQbe = ClienteListadoFiltros::tieneCriteriosAplicados($filtros ?? []);
    $vistasListado = $vistasListado ?? collect();
    $vistaActiva = $vistaActiva ?? null;
    $workbenchListo = $workbenchListo ?? false;
@endphp

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')

        <div class="card card-info lw-workbench shadow-sm">
            <div class="card-header lw-header d-flex flex-wrap align-items-center justify-content-between">
                <h3 class="card-title mb-0">
                    <i class="fa fa-users mr-1"></i> Clientes
                    <small class="ml-2" style="opacity:.85;font-weight:400;">Workbench · consulta multi-campo</small>
                </h3>
                <div class="card-tools ml-auto d-flex flex-wrap align-items-center justify-content-end" style="gap:.4rem;">
                    @if (can('crear-clientes', false))
                        <a href="{{ route('crear_cliente', $retornoListadoQuery) }}" class="btn btn-light btn-sm">
                            <i class="fa fa-plus"></i> Nuevo cliente
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

            <form method="get" action="{{ route('cliente') }}" id="form-filtros-cliente" class="mb-0">
                <input type="hidden" name="filtro_busqueda_rapida" id="filtro_busqueda_rapida" value="">
                <input type="hidden" name="filtro_modo" id="filtro_modo" value="{{ $filtros['modo'] ?? 'todos' }}">
                <input type="hidden" name="filtro_valor" id="filtro_valor" value="{{ $filtros['valor'] ?? '' }}">
                <input type="hidden" name="columnas" id="lw_columnas_csv" value="{{ implode(',', $columnasVisibles) }}">
                @if ($vistaActiva)
                    <input type="hidden" name="vista_id" value="{{ $vistaActiva->id }}">
                @endif

                <div class="lw-toolbar">
                    <div class="lw-toolbar-left">
                        <select id="lw-vista-select" class="form-control form-control-sm lw-vista-select"
                                data-base-url="{{ route('cliente') }}"
                                title="Vistas guardadas"
                                @if (! $workbenchListo) disabled @endif>
                            <option value="">Vista estándar</option>
                            @foreach ($vistasListado as $vista)
                                <option value="{{ $vista->id }}" @if ($vistaActiva && (int) $vistaActiva->id === (int) $vista->id) selected @endif>
                                    {{ $vista->nombre }}
                                    @if ($vista->es_default)
                                        ★
                                    @endif
                                    @if ($vista->compartida)
                                        (compartida)
                                    @endif
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
                        @if ($esBierzo)
                            <input type="text" name="filtro_codigo" id="filtro_codigo"
                                   class="form-control form-control-sm"
                                   style="width:110px;"
                                   value="{{ $filtroCodigo }}"
                                   placeholder="Código"
                                   autocomplete="off"
                                   title="Filtrar por código de cliente">
                        @endif
                        <input type="search" id="lw-search-rapida" class="form-control form-control-sm lw-search-rapida"
                               value="{{ ($filtros['modo'] ?? '') !== 'qbe' ? ($filtros['valor'] ?? '') : '' }}"
                               placeholder="Búsqueda rápida (Enter)…"
                               autocomplete="off">
                        <button type="button" id="btn-lw-buscar-rapida" class="btn btn-sm btn-primary">
                            <i class="fa fa-search"></i>
                        </button>
                        @if ($tieneQbe || (($filtros['valor'] ?? '') !== '') || $filtroCodigo !== '')
                            <a href="{{ $limpiarUrl }}" class="btn btn-sm btn-outline-warning">
                                <i class="fa fa-eraser"></i> Limpiar
                            </a>
                        @endif
                    </div>
                </div>

                @include('ventas.cliente.partials.workbench_qbe')

                @if ($tieneQbe)
                    <div class="lw-chips">
                        @if ($filtroCodigo !== '')
                            <span class="lw-chip"><strong>Código</strong> {{ $filtroCodigo }}</span>
                        @endif
                        @if (($filtros['modo'] ?? '') === 'qbe')
                            @php
                                $qbeChip = \App\Support\Listado\ListadoQbeSupport::paraUi($filtros['qbe'] ?? []);
                            @endphp
                            @foreach ($qbeChip['grupos'] as $gi => $grupo)
                                @if ($gi > 0)
                                    <span class="lw-chip lw-chip-logic">{{ ($qbeChip['entre_grupos'] ?? 'and') === 'or' ? 'O' : 'Y' }}</span>
                                @endif
                                @if (! empty($grupo['not']))
                                    <span class="lw-chip lw-chip-logic">NOT</span>
                                @endif
                                <span class="lw-chip lw-chip-logic">{{ ($grupo['logic'] ?? 'and') === 'or' ? 'Alguno' : 'Todos' }}</span>
                                @foreach (($grupo['criterios'] ?? []) as $c)
                                    @if (($c['op'] ?? '') === 'vacio' || ($c['op'] ?? '') === 'entre' || trim((string) ($c['valor'] ?? '')) !== '')
                                        <span class="lw-chip">
                                            <strong>{{ $etiquetasColumnas[$c['campo'] ?? ''] ?? ($c['campo'] ?? '') }}</strong>
                                            {{ $c['op'] ?? 'contiene' }}
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
                            <span class="lw-chip">
                                <strong>Texto</strong> {{ $filtros['valor'] }}
                            </span>
                        @endif
                        @php
                            $ordenChips = \App\Support\Listado\ListadoOrdenamientoSupport::normalizar(
                                $filtros['orden'] ?? [],
                                ClienteListadoFiltros::camposOrdenables()
                            );
                        @endphp
                        @foreach ($ordenChips as $oc)
                            <span class="lw-chip">
                                <i class="fa fa-sort"></i>
                                <strong>{{ $etiquetasColumnas[$oc['campo']] ?? $oc['campo'] }}</strong>
                                {{ $oc['dir'] === 'desc' ? '↓' : '↑' }}
                            </span>
                        @endforeach
                        @php
                            $agruparChips = \App\Support\Listado\ListadoAgrupacionSupport::normalizar(
                                $filtros['agrupar'] ?? [],
                                ClienteListadoFiltros::camposOrdenables()
                            );
                        @endphp
                        @foreach ($agruparChips as $ac)
                            <span class="lw-chip">
                                <i class="fa fa-object-group"></i>
                                <strong>{{ $etiquetasColumnas[$ac] ?? $ac }}</strong>
                            </span>
                        @endforeach
                    </div>
                @endif
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
                $usaScrollHorizontal = $sumaAnchosPantalla > LwGrilla::ANCHO_PRESUPUESTO_PANTALLA;
                $anchoAcciones = LwGrilla::ANCHO_COL_ACCIONES;
                $pctDatosDisponible = $usaScrollHorizontal
                    ? 100.0
                    : round(100 * $sumaAnchosPantalla / ($sumaAnchosPantalla + $anchoAcciones), 2);
                $camposOrdenablesThead = ClienteListadoFiltros::camposOrdenables();
                $ordenActualThead = \App\Support\Listado\ListadoOrdenamientoSupport::normalizar(
                    $filtros['orden'] ?? [],
                    $camposOrdenablesThead
                );
            @endphp
            <div class="px-3 pt-2">
                @include('includes.listado.workbench_cortes', ['cortes' => $cortes ?? []])
            </div>
            <div class="card-body p-0 lw-table-wrap {{ $usaScrollHorizontal ? 'table-responsive lw-table-scroll' : 'lw-table-fit' }}">
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'lista_cliente',
                    'queryparams' => $filtrosQuery ?? [],
                ])
                <table class="table table-striped table-bordered table-hover mb-0 lw-tabla-grilla" id="tabla-paginada">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            @foreach ($columnasVisibles as $key)
                                @php
                                    $cfg = $layoutPorKey[$key] ?? null;
                                    $cfgPantalla = $layoutPantallaPorKey[$key] ?? $cfg;
                                    $alinea = $cfg['alinea'] ?? 'izquierda';
                                    $ancho = (int) ($cfgPantalla['ancho'] ?? 100);
                                    $minLegible = LwGrilla::anchoMinimoLegible($key, $catalogoColumnas[$key] ?? []);
                                    $cls = LwGrilla::claseAlineacion($alinea);
                                    $tituloCol = $etiquetasColumnas[$key] ?? ($catalogoColumnas[$key]['label'] ?? $key);
                                    if ($usaScrollHorizontal) {
                                        $styleCol = 'width:'.$ancho.'px;min-width:'.$minLegible.'px;';
                                    } else {
                                        $pct = round(($ancho / $sumaAnchosPantalla) * $pctDatosDisponible, 2);
                                        $styleCol = 'width:'.$pct.'%;min-width:'.$minLegible.'px;';
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
                                <th class="{{ $cls }} lw-col {{ $esOrdenable ? 'lw-col-sortable' : '' }} {{ $dirCol ? 'lw-col-sorted' : '' }}"
                                    style="{{ $styleCol }}" title="{{ $tituloCol }}{{ $esOrdenable ? ' — clic para ordenar' : '' }}">
                                    @if ($esOrdenable)
                                        <a href="{{ route('cliente', $qsSort) }}" class="lw-sort-link">
                                            {{ $tituloCol }}
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
                                        {{ $tituloCol }}
                                    @endif
                                </th>
                            @endforeach
                            <th class="lw-col-acciones" data-orderable="false"
                                style="width:{{ $anchoAcciones }}px;min-width:{{ $anchoAcciones }}px;max-width:{{ $anchoAcciones }}px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $agruparActivo = \App\Support\Listado\ListadoAgrupacionSupport::normalizar(
                                $filtros['agrupar'] ?? [],
                                $camposOrdenablesThead
                            );
                            $filasVista = \App\Support\Listado\ListadoAgrupacionSupport::segmentar(
                                $clientes,
                                $agruparActivo,
                                static fn ($row, string $campo): string => ClienteListadoColumnas::valorCelda($row, $campo),
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
                                        <span class="lw-group-count" title="{{ ! empty($filaVista['count_universo']) ? 'Universo filtrado' : 'Página visible' }}">
                                            {{ number_format((int) ($filaVista['count'] ?? 0), 0, ',', '.') }}
                                        </span>
                                    </td>
                                </tr>
                            @else
                                @php $data = $filaVista['row']; @endphp
                                <tr @if ($data->estado == '1') class="table-danger" @elseif ($data->estado == 'R') class="table-warning" @endif>
                                    @foreach ($columnasVisibles as $key)
                                        @include('ventas.cliente.partials.workbench_celda', [
                                            'key' => $key,
                                            'data' => $data,
                                            'cfg' => $layoutPorKey[$key] ?? null,
                                        ])
                                    @endforeach
                                    <td class="lw-col-acciones text-nowrap"
                                        style="width:{{ $anchoAcciones }}px;min-width:{{ $anchoAcciones }}px;max-width:{{ $anchoAcciones }}px;">
                                        <div class="lw-acciones-inner">
                                            @if (can('editar-clientes', false))
                                                <a href="{{ route('editar_cliente', ['id' => $data->id] + $retornoListadoQuery) }}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                                    <i class="fa fa-edit"></i>
                                                </a>
                                            @endif
                                            @if (can('listar-cuentacorriente-cliente', false))
                                                <a href="{{ route('listar_cuentacorriente_cliente', ['id' => $data->id]) }}" class="btn-accion-tabla tooltipsC" title="Cuenta Corriente">
                                                    <i class="fa fa-folder-open"></i>
                                                </a>
                                            @endif
                                            @if (can('borrar-clientes', false))
                                                <form action="{{ route('eliminar_cliente', ['id' => $data->id]) }}" class="d-inline-flex form-eliminar mb-0" method="POST">
                                                    @csrf @method('delete')
                                                    <button type="submit" class="btn-accion-tabla eliminar tooltipsC" title="Eliminar este registro">
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
                @if ($agruparActivo !== [])
                    <div class="lw-group-note">
                        @if (! empty($cortes['activo']))
                            Conteos de cabecera = universo del filtro (Pack C).
                            @if (! empty($cortes['truncado']))
                                Panel de cortes truncado a {{ \App\Support\Listado\ListadoCortesSupport::MAX_FILAS }} filas.
                            @endif
                        @else
                            Conteo de grupos sobre la página visible.
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
{{ $clientes->appends($filtrosQuery ?? [])->links() }}

@include('ventas.cliente.partials.workbench_modales')
@endsection
