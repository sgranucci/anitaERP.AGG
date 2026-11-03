@extends("theme.$theme.layout")
@section('titulo')
    Solicitudes de pago
@endsection

@section('styles')
<link rel="stylesheet" href="{{ asset('assets/css/listado-workbench.css') }}?v={{ filemtime(public_path('assets/css/listado-workbench.css')) }}">
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/includes/listado-filtros.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/solicitudpago/solicitudpago/filtro.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/solicitudpago/solicitudpago/familia_vinculos.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/solicitudpago/solicitudpago/anular_revertir_pago.js")}}" type="text/javascript"></script>
@php
    $qbeGruposJs = public_path('assets/pages/scripts/listado/workbench-qbe-grupos.js');
    $ordenJs = public_path('assets/pages/scripts/listado/workbench-orden.js');
    $agruparJs = public_path('assets/pages/scripts/listado/workbench-agrupar.js');
    $disenadorJs = public_path('assets/pages/scripts/listado/workbench-disenador-preview.js');
    $vistaGuardarJs = public_path('assets/pages/scripts/listado/workbench-vista-guardar.js');
    $spWorkbenchJs = public_path('assets/pages/scripts/solicitudpago/solicitudpago/workbench.js');
@endphp
<script src="{{ asset('assets/pages/scripts/listado/workbench-qbe-grupos.js') }}?v={{ file_exists($qbeGruposJs) ? filemtime($qbeGruposJs) : time() }}"></script>
<script src="{{ asset('assets/pages/scripts/listado/workbench-orden.js') }}?v={{ file_exists($ordenJs) ? filemtime($ordenJs) : time() }}"></script>
<script src="{{ asset('assets/pages/scripts/listado/workbench-agrupar.js') }}?v={{ file_exists($agruparJs) ? filemtime($agruparJs) : time() }}"></script>
<script src="{{ asset('assets/pages/scripts/listado/workbench-disenador-preview.js') }}?v={{ file_exists($disenadorJs) ? filemtime($disenadorJs) : time() }}"></script>
<script src="{{ asset('assets/pages/scripts/listado/workbench-vista-guardar.js') }}?v={{ file_exists($vistaGuardarJs) ? filemtime($vistaGuardarJs) : time() }}"></script>
<script src="{{ asset('assets/pages/scripts/solicitudpago/solicitudpago/workbench.js') }}?v={{ file_exists($spWorkbenchJs) ? filemtime($spWorkbenchJs) : time() }}"></script>
@if (can('crear-solicitud-pago', false))
<script src="{{asset("assets/pages/scripts/solicitudpago/solicitudpago/carga_masiva.js")}}" type="text/javascript"></script>
@endif
@endsection

@php
    use App\Support\Solicitudpago\SolicitudpagoListadoFiltros;
    $retornoListadoQuery = \App\Support\Listado\QueryRetornoListado::retornoLinksDesdeFiltrosQuery($filtrosQuery ?? []);
    $qbeAbierto = \App\Support\Listado\ListadoQbeSupport::tieneCriterios($filtros['qbe'] ?? []);
    $columnasVisibles = $columnasVisibles ?? [];
    $catalogoColumnas = $catalogoColumnas ?? [];
    $etiquetasColumnas = $etiquetasColumnas ?? [];
@endphp

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info lw-workbench shadow-sm">
            <div class="card-header lw-header d-flex flex-wrap align-items-center justify-content-between">
                <h3 class="card-title mb-0">Solicitudes de pago</h3>
                <div class="card-tools ml-auto d-flex flex-wrap align-items-center justify-content-end" style="gap:.35rem;">
                    @if (can('programar-fecha-pago-solicitud', false))
                        <a href="{{ route('programar_fecha_pago_solicitud') }}" class="btn btn-primary btn-sm" title="Asignar la fecha en que se va a pagar">
                            <i class="fa fa-calendar"></i> Fecha de pago
                        </a>
                    @endif
                    @if (can('crear-solicitud-pago', false))
                        <button type="button" class="btn btn-success btn-sm" id="btn-carga-masiva-sp" title="Importar solicitudes desde CSV Anita">
                            <i class="fa fa-upload"></i> Carga masiva
                        </button>
                        <a href="{{ route('crear_solicitudpago', $retornoListadoQuery) }}" class="btn btn-light btn-sm">
                            <i class="fa fa-plus"></i> Nuevo
                        </a>
                    @endif
                    @if (!empty($puedeVerTodas) && !empty($alcanceToggleUrl))
                        @if (($alcanceListado ?? 'todas') === 'mi_cc')
                            <a href="{{ $alcanceToggleUrl }}" class="btn btn-warning btn-sm" title="Mostrar todas las solicitudes sin filtrar por centro de costo">
                                <i class="fa fa-globe"></i> Ver todas
                            </a>
                        @else
                            <a href="{{ $alcanceToggleUrl }}" class="btn btn-outline-light btn-sm" title="Filtrar solo las de su centro de costo">
                                <i class="fa fa-building"></i> Solo mi centro de costo
                            </a>
                        @endif
                    @endif
                </div>
            </div>
            @if (! ($workbenchListo ?? false))
                <div class="alert alert-warning lw-aviso-migracion mb-0">
                    <strong>Migración pendiente.</strong>
                    Para vistas y configuración de grilla hace falta la tabla <code>listado_vista</code>.
                </div>
            @endif
            <form method="get" action="{{ route('consultar_solicitudpago') }}" id="form-filtros-solicitudpago" class="mb-0">
                <input type="hidden" name="filtro_busqueda_rapida" id="filtro_busqueda_rapida" value="">
                <input type="hidden" name="filtro_modo" id="filtro_modo" value="{{ $filtros['modo'] ?? 'todos' }}">
                <input type="hidden" name="filtro_valor" id="filtro_valor" value="{{ $filtros['valor'] ?? '' }}">
                <input type="hidden" name="columnas" id="lw_columnas_csv" value="{{ implode(',', $columnasVisibles) }}">
                @if (($alcanceListado ?? '') === 'mi_cc')
                    <input type="hidden" name="alcance" value="mi_cc">
                @endif
                @if ($vistaActiva ?? null)
                    <input type="hidden" name="vista_id" value="{{ $vistaActiva->id }}">
                @endif
                <div class="lw-toolbar">
                    <div class="lw-toolbar-left">
                        <select id="lw-vista-select" class="form-control form-control-sm lw-vista-select"
                                data-base-url="{{ route('consultar_solicitudpago') }}"
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
                        <button type="button" class="btn btn-sm {{ $qbeAbierto ? 'btn-info' : 'btn-outline-info' }} {{ $qbeAbierto ? '' : 'collapsed' }}"
                                data-toggle="collapse" data-target="#lw-qbe-panel" aria-expanded="{{ $qbeAbierto ? 'true' : 'false' }}" aria-controls="lw-qbe-panel">
                            <i class="fa fa-filter"></i> QBE
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-info collapsed" data-toggle="collapse" data-target="#lw-analisis-panel" aria-expanded="false" title="Gráfico y color de fila">
                            <i class="fa fa-bar-chart"></i> Visual
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-toggle="modal" data-target="#modal-lw-etiquetas"
                                @if (! ($workbenchListo ?? false)) disabled title="Requiere migración" @endif>
                            <i class="fa fa-font"></i> Defaults instalación
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-toggle="collapse" data-target="#panel-filtros-solicitudpago">
                            <i class="fa fa-sliders"></i> Filtros
                        </button>
                    </div>
                    <div class="lw-toolbar-right">
                        <input type="search" id="lw-search-rapida" class="form-control form-control-sm lw-search-rapida"
                               value="{{ ($filtros['modo'] ?? '') !== 'qbe' ? ($filtros['valor'] ?? '') : '' }}"
                               placeholder="Texto o número" autocomplete="off">
                        <button type="submit" id="btn-lw-buscar-rapida" class="btn btn-sm btn-primary" data-busqueda-rapida="1">
                            <i class="fa fa-search"></i>
                        </button>
                        @if (SolicitudpagoListadoFiltros::tieneCriteriosAplicados($filtros ?? []))
                            <a href="{{ $limpiarFiltrosUrl ?? route('consultar_solicitudpago', ['limpiar_filtros' => 1]) }}" class="btn btn-sm btn-outline-warning">
                                <i class="fa fa-eraser"></i> Limpiar
                            </a>
                        @endif
                        <span class="lw-toolbar-export">
                            @include('includes.exportar-tabla-queryparams', [
                                'ruta' => 'lista_solicitudpago',
                                'queryparams' => $filtrosQuery ?? [],
                                'variant' => 'compact',
                            ])
                        </span>
                    </div>
                </div>
                @include('solicitudpago.solicitudpago.partials.filtros_listado', [
                    'limpiarUrl' => $limpiarFiltrosUrl ?? route('consultar_solicitudpago', ['limpiar_filtros' => 1]),
                ])
                @include('solicitudpago.solicitudpago.partials.workbench_qbe')
                @php
                    $ejesVisual = SolicitudpagoListadoFiltros::camposOrdenables();
                    $medidasVisual = \App\Support\Listado\ListadoVisualSupport::medidasDisponibles($ejesVisual);
                    $lienzoVisual = true;
                    $calculadasVisual = true;
                @endphp
                @include('includes.listado.workbench_visual')
            </form>
            @include('solicitudpago.solicitudpago.partials.filtros_externos')
            @include('includes.listado.workbench_grafico', [
                'recursoVisual' => \App\Support\Solicitudpago\SolicitudpagoListadoColumnas::RECURSO,
                'rutaListadoVisual' => 'consultar_solicitudpago',
            ])
            @if (!empty($puedeVerTodas) && ($alcanceListado ?? '') === 'mi_cc')
                <div class="alert alert-warning py-2 mb-0 mx-3 mt-2 small">
                    Alcance: <strong>solo su centro de costo</strong>. Use <strong>Ver todas</strong> para listar sin esa restricción.
                </div>
            @endif
            @php
                use App\Support\Listado\ListadoGrillaConfigSupport as LwGrilla;
                $layoutPorKey = [];
                foreach (($grillaLayout ?? []) as $filaLayout) {
                    $layoutPorKey[$filaLayout['key']] = $filaLayout;
                }
                $camposOrdenablesThead = SolicitudpagoListadoFiltros::camposOrdenables();
                $ordenActualThead = \App\Support\Listado\ListadoOrdenamientoSupport::normalizar(
                    $filtros['orden'] ?? [],
                    $camposOrdenablesThead
                );
            @endphp
            <div class="px-3 pt-2">
                @include('includes.listado.workbench_cortes', ['cortes' => $cortes ?? []])
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-striped table-bordered table-hover mb-0 lw-tabla-grilla" id="tabla-paginada">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            @foreach ($columnasVisibles as $key)
                                @php
                                    $tituloCol = $etiquetasColumnas[$key] ?? ($catalogoColumnas[$key]['label'] ?? $key);
                                    $esOrdenable = isset($camposOrdenablesThead[$key]);
                                    $dirCol = \App\Support\Listado\ListadoOrdenamientoSupport::direccionDeCampo($ordenActualThead, $key);
                                    $idxCol = \App\Support\Listado\ListadoOrdenamientoSupport::indiceDeCampo($ordenActualThead, $key);
                                    $qsSort = $filtrosQuery ?? [];
                                    unset($qsSort['sort']);
                                    if ($esOrdenable) {
                                        $ordenToggle = \App\Support\Listado\ListadoOrdenamientoSupport::togglePrimario($ordenActualThead, $key, $camposOrdenablesThead);
                                        $qsSort = array_merge($qsSort, \App\Support\Listado\ListadoOrdenamientoSupport::paraQueryString($ordenToggle));
                                    }
                                    $alinea = $layoutPorKey[$key]['alinea'] ?? 'izquierda';
                                @endphp
                                <th class="{{ LwGrilla::claseAlineacion($alinea) }} {{ $esOrdenable ? 'lw-col-sortable' : '' }}">
                                    @if ($esOrdenable)
                                        <a href="{{ route('consultar_solicitudpago', $qsSort) }}" class="lw-sort-link">
                                            {{ $tituloCol }}
                                            @if ($dirCol === 'asc')
                                                <i class="fa fa-sort-up"></i>
                                            @elseif ($dirCol === 'desc')
                                                <i class="fa fa-sort-down"></i>
                                            @else
                                                <i class="fa fa-sort text-muted"></i>
                                            @endif
                                            @if ($idxCol !== null && count($ordenActualThead) > 1)
                                                <sup>{{ $idxCol + 1 }}</sup>
                                            @endif
                                        </a>
                                    @else
                                        {{ $tituloCol }}
                                    @endif
                                </th>
                            @endforeach
                            @foreach (($filtros['calculadas'] ?? []) as $calc)
                                <th>{{ $calc['etiqueta'] ?? '' }}</th>
                            @endforeach
                            <th class="width120 text-nowrap" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $agruparActivo = \App\Support\Listado\ListadoAgrupacionSupport::normalizar($filtros['agrupar'] ?? [], $camposOrdenablesThead);
                            $filasVista = \App\Support\Listado\ListadoAgrupacionSupport::segmentar(
                                $coleccion,
                                $agruparActivo,
                                static fn ($row, string $campo): string => \App\Support\Solicitudpago\SolicitudpagoListadoColumnas::valorCelda($row, $campo),
                                $etiquetasColumnas,
                                ! empty($cortes['por_clave']) ? $cortes['por_clave'] : null
                            );
                            $colspanGrilla = count($columnasVisibles) + count($filtros['calculadas'] ?? []) + 1;
                        @endphp
                        @forelse ($filasVista as $filaVista)
                            @if (($filaVista['type'] ?? '') === 'header')
                                <tr class="lw-group-header lw-group-nivel-{{ (int) ($filaVista['nivel'] ?? 0) }}">
                                    <td colspan="{{ $colspanGrilla }}">
                                        <strong>{{ $filaVista['label'] }}:</strong>
                                        {{ $filaVista['valor'] }}
                                        <span class="badge badge-light border">{{ number_format((int) ($filaVista['count'] ?? 0), 0, ',', '.') }}</span>
                                    </td>
                                </tr>
                            @else
                                @php
                                    $data = $filaVista['row'];
                                    $esSuspendidaFila = ($data->estado ?? '') === \App\Support\Solicitudpago\SolicitudpagoEstados::SUSPENDIDA;
                                    $tono = \App\Support\Listado\ListadoVisualSupport::tonoFila(
                                        $data,
                                        $filtros['formato'] ?? [],
                                        static fn ($row, string $campo): string => \App\Support\Solicitudpago\SolicitudpagoListadoColumnas::valorCelda($row, $campo)
                                    );
                                @endphp
                                <tr class="{{ $esSuspendidaFila ? 'table-secondary' : '' }} {{ $tono ? 'lw-tono-'.$tono : '' }}">
                                    @foreach ($columnasVisibles as $key)
                                        @include('solicitudpago.solicitudpago.partials.workbench_celda', [
                                            'key' => $key,
                                            'data' => $data,
                                            'cfg' => $layoutPorKey[$key] ?? null,
                                            'retornoListadoQuery' => $retornoListadoQuery,
                                        ])
                                    @endforeach
                                    @foreach (($filtros['calculadas'] ?? []) as $iCalc => $calc)
                                        <td>{{ $data->{'calc_'.$iCalc} ?? '' }}</td>
                                    @endforeach
                                    <td class="text-nowrap">
                                        @include('solicitudpago.solicitudpago.partials.fila_acciones', ['data' => $data, 'retornoListadoQuery' => $retornoListadoQuery])
                                    </td>
                                </tr>
                            @endif
                        @empty
                            <tr>
                                <td colspan="{{ max(1, $colspanGrilla) }}">Sin solicitudes con esos criterios.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
{{ $coleccion->appends($filtrosQuery ?? [])->links() }}
@include('solicitudpago.solicitudpago.partials.workbench_modales')
@include('includes.solicitudpago.modal_familia_vinculos')
@if (can('crear-solicitud-pago', false))
    @include('solicitudpago.solicitudpago.partials.modal_carga_masiva')
@endif
@endsection
