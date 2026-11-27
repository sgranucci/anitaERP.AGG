@extends("theme.$theme.layout")
@section('titulo')
    Aplicaciones de cuenta corriente
@endsection

@section("styles")
<link rel="stylesheet" href="{{ asset('assets/css/listado-workbench.css') }}?v={{ filemtime(public_path('assets/css/listado-workbench.css')) }}">
<link rel="stylesheet" href="{{ asset('assets/css/tabla-ancha-reporte.css') }}?v={{ @filemtime(public_path('assets/css/tabla-ancha-reporte.css')) ?: time() }}">
<style>
.lw-workbench .apl-cc-grilla {
    --tabla-ancha-der: 7rem;
}
.lw-workbench .apl-cc-grilla #tabla-paginada th.lw-col-acciones,
.lw-workbench .apl-cc-grilla #tabla-paginada td.lw-col-acciones {
    width: var(--tabla-ancha-der);
    min-width: var(--tabla-ancha-der);
    max-width: none;
    white-space: nowrap;
    overflow: visible;
    text-align: center;
    vertical-align: middle;
}
/* Sin puntos suspensivos: el texto largo se parte en renglones. */
.lw-workbench .apl-cc-grilla #tabla-paginada td.lw-col {
    white-space: normal;
    overflow: visible;
    text-overflow: clip;
    max-width: none;
    word-break: break-word;
}
.lw-workbench .apl-cc-grilla #tabla-paginada .lw-celda-texto {
    display: block;
    white-space: normal;
    overflow: visible;
    text-overflow: clip;
    word-break: break-word;
}
.lw-workbench .apl-cc-grilla #tabla-paginada td.lw-col strong.text-nowrap { white-space: nowrap; }
/* Títulos de columna completos: parten en renglones en vez de cortarse. */
.lw-workbench .apl-cc-grilla #tabla-paginada th.lw-col .lw-sort-link,
.lw-workbench .apl-cc-grilla #tabla-paginada th.lw-col .lw-sort-link .lw-th-label,
.lw-workbench .apl-cc-grilla #tabla-paginada th.lw-col .lw-th-titulo {
    white-space: normal;
    text-align: inherit;
    line-height: 1.15;
    word-break: normal;
    overflow-wrap: normal;
    hyphens: none;
}
.lw-workbench .apl-cc-grilla #tabla-paginada th.lw-col { word-break: normal; overflow-wrap: normal; }
.lw-workbench .apl-cc-resumen strong { color: #17202A; }
.lw-workbench tr.apl-cc-anulado td { color: #7B7D7D; }
</style>
@endsection

@section("scripts")
@php
    $jsListado = [
        'assets/pages/scripts/listado/workbench-qbe-grupos.js',
        'assets/pages/scripts/listado/workbench-orden.js',
        'assets/pages/scripts/listado/workbench-agrupar.js',
        'assets/pages/scripts/listado/workbench-disenador-preview.js',
        'assets/pages/scripts/listado/workbench-vista-guardar.js',
        'assets/pages/scripts/listado/workbench-columnas-dnd.js',
        'assets/pages/scripts/admin/tabla-ancha-reporte.js',
        'assets/pages/scripts/ventas/aplicacion_cc_comprobante/workbench.js',
    ];
@endphp
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
@foreach ($jsListado as $js)
    <script src="{{ asset($js) }}?v={{ @filemtime(public_path($js)) ?: time() }}"></script>
@endforeach
@endsection

@php
    use App\Models\Ventas\AplicacionCcClienteComprobante;
    use App\Support\Ventas\AplicacionCcClienteComprobanteListadoColumnas;
    use App\Support\Ventas\AplicacionCcClienteComprobanteListadoFiltros;
    use App\Support\Listado\ListadoGrillaConfigSupport as LwGrilla;

    $columnasVisibles = $columnasVisibles ?? [];
    $camposOrdenablesThead = AplicacionCcClienteComprobanteListadoFiltros::camposOrdenables();
    $ordenActualThead = \App\Support\Listado\ListadoOrdenamientoSupport::normalizar($filtros['sort'] ?? [], $camposOrdenablesThead);
    $qsQuitarOrden = $filtrosQuery ?? [];
    unset($qsQuitarOrden['sort']);
    $qsQuitarOrden['quitar_orden'] = 1;
    $urlQuitarOrden = route('aplicacion_cc_cliente', $qsQuitarOrden);
    $limpiarUrl = route('aplicacion_cc_cliente', ['limpiar_filtros' => 1]);
    $urlNueva = route('aplicacion_cuentacorriente_cliente', ! empty($filtros['cliente_id']) ? ['cliente_id' => (int) $filtros['cliente_id']] : []);
    $puedeVerCliente = can('editar-clientes', false) || can('listar-clientes', false);
@endphp

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info lw-workbench shadow-sm">
            <div class="card-header lw-header d-flex flex-wrap align-items-center justify-content-between">
                <h3 class="card-title mb-0">Aplicaciones de cuenta corriente — clientes</h3>
                <div class="card-tools ml-auto d-flex flex-wrap align-items-center justify-content-end" style="gap:.4rem;">
                    <a href="{{ $urlNueva }}" class="btn btn-light btn-sm">
                        <i class="fa fa-plus"></i> Nueva aplicación
                    </a>
                </div>
            </div>
            @if (! ($workbenchListo ?? false))
                <div class="alert alert-warning lw-aviso-migracion mb-0">
                    <strong>Migración pendiente.</strong>
                    Para vistas y configuración de grilla hace falta la tabla <code>listado_vista</code>.
                </div>
            @endif
            <form method="get" action="{{ route('aplicacion_cc_cliente') }}" id="form-filtros-aplicacion-cc" class="mb-0">
                <input type="hidden" name="filtro_busqueda_rapida" id="filtro_busqueda_rapida" value="">
                <input type="hidden" name="filtro_modo" id="filtro_modo" value="{{ $filtros['modo'] ?? 'todos' }}">
                <input type="hidden" name="filtro_valor" id="filtro_valor" value="{{ $filtros['valor'] ?? '' }}">
                <input type="hidden" name="columnas" id="lw_columnas_csv" value="{{ implode(',', $columnasVisibles) }}">
                @foreach (['filtro_estado', 'empresa_id', 'empresa_todas', 'cliente_id'] as $externo)
                    @if (isset($filtrosQuery[$externo]))
                        <input type="hidden" name="{{ $externo }}" value="{{ $filtrosQuery[$externo] }}">
                    @endif
                @endforeach
                @if ($vistaActiva ?? null)
                    <input type="hidden" name="vista_id" value="{{ $vistaActiva->id }}">
                @endif
                <div class="lw-toolbar">
                    <div class="lw-toolbar-left">
                        <select id="lw-vista-select" class="form-control form-control-sm lw-vista-select"
                                data-base-url="{{ route('aplicacion_cc_cliente') }}"
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
                        <button type="button" class="btn btn-sm btn-outline-info collapsed" data-toggle="collapse" data-target="#lw-analisis-panel" aria-expanded="false" title="Gráfico">
                            <i class="fa fa-bar-chart"></i> Visual
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-toggle="modal" data-target="#modal-lw-etiquetas"
                                @if (! ($workbenchListo ?? false)) disabled title="Requiere migración" @endif>
                            <i class="fa fa-font"></i> Defaults instalación
                        </button>
                    </div>
                    <div class="lw-toolbar-right">
                        <input type="search" id="lw-search-rapida" class="form-control form-control-sm lw-search-rapida"
                               value="{{ ($filtros['modo'] ?? '') !== 'qbe' ? ($filtros['valor'] ?? '') : '' }}"
                               placeholder="Número, cliente, comprobante…"
                               autocomplete="off">
                        <button type="button" id="btn-lw-buscar-rapida" class="btn btn-sm btn-primary">
                            <i class="fa fa-search"></i>
                        </button>
                        @if (AplicacionCcClienteComprobanteListadoFiltros::tieneCriteriosAplicados($filtros ?? []))
                            <a href="{{ $limpiarUrl }}" class="btn btn-sm btn-outline-warning">
                                <i class="fa fa-eraser"></i> Limpiar filtros
                            </a>
                        @endif
                        @if (($ordenActualThead ?? []) !== [])
                            <a href="{{ $urlQuitarOrden }}" class="btn btn-sm btn-outline-secondary" title="Vuelve al orden por fecha descendente">
                                <i class="fa fa-undo"></i> Quitar orden
                            </a>
                        @endif
                    </div>
                </div>
                @include('ventas.aplicacion_cc_comprobante.partials.filtros_externos')
                @include('ventas.aplicacion_cc_comprobante.partials.workbench_qbe')
                @php
                    $ejesVisual = AplicacionCcClienteComprobanteListadoFiltros::camposOrdenables();
                    $medidasVisual = \App\Support\Listado\ListadoVisualSupport::medidasDisponibles($ejesVisual);
                    $lienzoVisual = true;
                @endphp
                @include('includes.listado.workbench_visual')
            </form>
            @include('includes.listado.workbench_grafico', [
                'recursoVisual' => AplicacionCcClienteComprobanteListadoColumnas::RECURSO,
                'rutaListadoVisual' => 'aplicacion_cc_cliente',
            ])
            @php
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
                $anchoAcciones = LwGrilla::ANCHO_COL_ACCIONES;
                $pctDatosDisponible = $usaScrollHorizontal
                    ? 100.0
                    : round(100 * $sumaAnchosPantalla / ($sumaAnchosPantalla + $anchoAcciones), 2);
                $estiloCol = static function (string $keyColumna) use ($usaScrollHorizontal, $layoutPantallaPorKey, $sumaAnchosPantalla, $pctDatosDisponible): string {
                    $ancho = (int) (($layoutPantallaPorKey[$keyColumna] ?? [])['ancho'] ?? 100);

                    return $usaScrollHorizontal
                        ? 'width:'.$ancho.'px;'
                        : 'width:'.round(($ancho / $sumaAnchosPantalla) * $pctDatosDisponible, 2).'%;';
                };
            @endphp
            <div class="px-3 pt-2">
                @include('includes.listado.workbench_cortes', ['cortes' => $cortes ?? []])
            </div>
            <div class="card-body py-2 border-bottom bg-white d-flex flex-wrap align-items-center justify-content-between" style="gap:.5rem;">
                <div class="apl-cc-resumen small text-muted">
                    <i class="fa fa-calculator"></i>
                    <strong>{{ number_format((int) ($resumen['total'] ?? 0), 0, ',', '.') }}</strong> comprobante(s)
                    · Total <strong>$ {{ number_format((float) ($resumen['total_pesos'] ?? 0), 2, ',', '.') }}</strong>
                    @if (abs((float) ($resumen['diferencia_cambio'] ?? 0)) >= 0.01)
                        · Dif. cambio <strong>$ {{ number_format((float) $resumen['diferencia_cambio'], 2, ',', '.') }}</strong>
                    @endif
                    <span class="ml-1" title="Totales del filtro completo, no solo de la página">(filtro completo)</span>
                </div>
            </div>
            <div class="tabla-ancha-grilla apl-cc-grilla" data-tabla-ancha>
                <div class="tabla-ancha-scroll-top" hidden>
                    <div class="tabla-ancha-scroll-top-inner"></div>
                </div>
                <div class="tabla-ancha-wrap card-body p-0 lw-table-wrap {{ $usaScrollHorizontal ? 'table-responsive lw-table-scroll' : 'lw-table-fit' }}">
                    <table class="tabla-ancha table table-striped table-bordered table-hover mb-0 lw-tabla-grilla" id="tabla-paginada">
                    <thead style="background-color:#85C1E9;color:#17202A;">
                        <tr>
                            @foreach ($columnasVisibles as $keyColumna)
                                @php
                                    $cfg = $layoutPorKey[$keyColumna] ?? null;
                                    $cfgPantalla = $layoutPantallaPorKey[$keyColumna] ?? $cfg;
                                    $tituloCol = $etiquetasColumnas[$keyColumna] ?? ($catalogoColumnas[$keyColumna]['label'] ?? $keyColumna);
                                    $minLegible = LwGrilla::anchoMinimoLegible($keyColumna, $catalogoColumnas[$keyColumna] ?? [], $tituloCol);
                                    $styleCol = $usaScrollHorizontal
                                        ? 'width:'.((int) ($cfgPantalla['ancho'] ?? 100)).'px;min-width:'.$minLegible.'px;'
                                        : $estiloCol($keyColumna).'min-width:0;';
                                @endphp
                                @include('ventas.aplicacion_cc_comprobante.partials.workbench_th', [
                                    'key' => $keyColumna,
                                    'styleCol' => $styleCol,
                                    'clsAlinea' => LwGrilla::claseAlineacion($cfg['alinea'] ?? 'izquierda'),
                                    'tituloOverride' => $tituloCol,
                                ])
                            @endforeach
                            <th class="lw-col-acciones col-fija-der-1" data-orderable="false"
                                style="width:{{ $anchoAcciones }}px;min-width:{{ $anchoAcciones }}px;max-width:{{ $anchoAcciones }}px;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $filasVista = \App\Support\Listado\ListadoAgrupacionSupport::segmentar(
                                $datas,
                                \App\Support\Listado\ListadoAgrupacionSupport::normalizar($filtros['agrupar'] ?? [], $camposOrdenablesThead),
                                static fn ($row, string $campo): string => AplicacionCcClienteComprobanteListadoColumnas::valorCelda($row, $campo),
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
                                        <span class="lw-group-count" title="{{ ! empty($filaVista['count_universo']) ? 'Universo filtrado' : 'Página visible' }}">
                                            {{ number_format((int) ($filaVista['count'] ?? 0), 0, ',', '.') }}
                                        </span>
                                    </td>
                                </tr>
                            @else
                                @php
                                    $data = $filaVista['row'];
                                    $anulado = $data->estado === AplicacionCcClienteComprobante::ESTADO_ANULADO;
                                @endphp
                                <tr class="{{ $anulado ? 'apl-cc-anulado' : '' }}">
                                    @foreach ($columnasVisibles as $keyColumna)
                                        @include('ventas.aplicacion_cc_comprobante.partials.workbench_celda', [
                                            'key' => $keyColumna,
                                            'data' => $data,
                                            'cfg' => $layoutPorKey[$keyColumna] ?? null,
                                            'styleCol' => $estiloCol($keyColumna),
                                        ])
                                    @endforeach
                                    <td class="lw-col-acciones col-fija-der-1 text-nowrap"
                                        style="width:{{ $anchoAcciones }}px;min-width:{{ $anchoAcciones }}px;max-width:{{ $anchoAcciones }}px;">
                                        <div class="lw-acciones-inner">
                                            <a href="{{ route('imprimir_aplicacion_cc_cliente', ['id' => $data->id]) }}"
                                               class="btn-accion-tabla tooltipsC" title="Imprimir comprobante" target="_blank" rel="noopener">
                                                <i class="fa fa-print"></i>
                                            </a>
                                            @if (! $anulado && ($puedeAnular ?? false))
                                                <form action="{{ route('anular_aplicacion_cc_cliente', ['id' => $data->id]) }}" method="POST"
                                                      class="d-inline-flex mb-0 form-anular-aplicacion-cc"
                                                      data-numero="{{ $data->numeroFormateado() }}"
                                                      data-lineas="{{ (int) $data->cantidad_lineas }}">
                                                    @csrf
                                                    @include('includes.listado.hidden_nested', [
                                                        'prefix' => '',
                                                        'data' => $filtrosQuery ?? [],
                                                        'skipKeys' => ['columnas', 'page'],
                                                    ])
                                                    <button type="submit" class="btn-accion-tabla tooltipsC" title="Anular: revierte todas sus aplicaciones">
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
                                <td colspan="{{ $colspanGrilla }}" class="text-center text-muted py-4">No hay comprobantes de aplicación con estos filtros.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                </div>
            </div>
            @if ($datas->total() > 0)
                <div class="card-footer py-2 d-flex flex-wrap align-items-center justify-content-between">
                    <span class="small text-muted">{{ $datas->firstItem() }}–{{ $datas->lastItem() }} de {{ number_format($datas->total(), 0, ',', '.') }}</span>
                    {{ $datas->appends($filtrosQuery ?? [])->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
@include('includes.listado.workbench_columnas_dnd', [
    'rutaGuardarColumnasDnd' => 'guardar_columnas_listado_aplicacion_cc_cliente',
    'paraQueryStringDnd' => [AplicacionCcClienteComprobanteListadoFiltros::class, 'paraQueryString'],
    'filtrosDnd' => $filtros ?? [],
])
@include('ventas.aplicacion_cc_comprobante.partials.workbench_modales')
@endsection
