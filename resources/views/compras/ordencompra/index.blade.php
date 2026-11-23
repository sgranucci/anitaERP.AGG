@extends("theme.$theme.layout")
@section('titulo')
Órdenes de compra
@endsection

@section('styles')
<link rel="stylesheet" href="{{ asset('assets/css/listado-workbench.css') }}?v={{ @filemtime(public_path('assets/css/listado-workbench.css')) ?: time() }}">
<link rel="stylesheet" href="{{ asset('assets/css/tabla-ancha-reporte.css') }}?v={{ @filemtime(public_path('assets/css/tabla-ancha-reporte.css')) ?: time() }}">
<link rel="stylesheet" href="{{ asset('assets/css/compras/ordencompra-ui.css') }}?v={{ @filemtime(public_path('assets/css/compras/ordencompra-ui.css')) ?: time() }}">
<link rel="stylesheet" href="{{ asset('assets/pages/css/compras/ordencompra/asignar_factura_legajo.css') }}?v={{ @filemtime(public_path('assets/pages/css/compras/ordencompra/asignar_factura_legajo.css')) ?: time() }}">
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
@php
    $qbeGruposJs = public_path('assets/pages/scripts/listado/workbench-qbe-grupos.js');
    $ordenJs = public_path('assets/pages/scripts/listado/workbench-orden.js');
    $agruparJs = public_path('assets/pages/scripts/listado/workbench-agrupar.js');
    $disenadorJs = public_path('assets/pages/scripts/listado/workbench-disenador-preview.js');
    $vistaGuardarJs = public_path('assets/pages/scripts/listado/workbench-vista-guardar.js');
    $workbenchJs = public_path('assets/pages/scripts/compras/ordencompra/workbench.js');
    $columnasDndJs = public_path('assets/pages/scripts/listado/workbench-columnas-dnd.js');
    $tablaAnchaJs = public_path('assets/pages/scripts/admin/tabla-ancha-reporte.js');
@endphp
<script src="{{ asset('assets/pages/scripts/listado/workbench-qbe-grupos.js') }}?v={{ file_exists($qbeGruposJs) ? filemtime($qbeGruposJs) : time() }}"></script>
<script src="{{ asset('assets/pages/scripts/listado/workbench-orden.js') }}?v={{ file_exists($ordenJs) ? filemtime($ordenJs) : time() }}"></script>
<script src="{{ asset('assets/pages/scripts/listado/workbench-agrupar.js') }}?v={{ file_exists($agruparJs) ? filemtime($agruparJs) : time() }}"></script>
<script src="{{ asset('assets/pages/scripts/listado/workbench-disenador-preview.js') }}?v={{ file_exists($disenadorJs) ? filemtime($disenadorJs) : time() }}"></script>
<script src="{{ asset('assets/pages/scripts/listado/workbench-vista-guardar.js') }}?v={{ file_exists($vistaGuardarJs) ? filemtime($vistaGuardarJs) : time() }}"></script>
<script src="{{ asset('assets/pages/scripts/compras/ordencompra/workbench.js') }}?v={{ file_exists($workbenchJs) ? filemtime($workbenchJs) : time() }}"></script>
<script src="{{ asset('assets/pages/scripts/listado/workbench-columnas-dnd.js') }}?v={{ file_exists($columnasDndJs) ? filemtime($columnasDndJs) : time() }}"></script>
<script src="{{ asset('assets/pages/scripts/admin/tabla-ancha-reporte.js') }}?v={{ file_exists($tablaAnchaJs) ? filemtime($tablaAnchaJs) : time() }}"></script>
<script src="{{ asset('assets/pages/scripts/compras/ordencompra/enviar-proveedor.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/ordencompra/cambiar_sector_legajo.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/compras/ordencompra/cambiar_sector_legajo.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/ordencompra/asignar_factura_legajo.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/compras/ordencompra/asignar_factura_legajo.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/includes/erp-workspace-panel.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/includes/erp-workspace-panel.js')) ?: time() }}" type="text/javascript"></script>
@include('compras.partials.documentos_relacionados_circuito_script')
@if (session('sugerir_envio_oc'))
<script>
    window.ocSugerirEnvioProveedor = { ordencompra_id: {{ (int) session('sugerir_envio_oc') }} };
</script>
@endif
<script>
$(function () {
    $('.js-oc-index-abrir-estado').on('click', function () {
        $('#formIndexOcEstado').attr('action', $(this).data('url'));
        var cur = $(this).data('estado-actual') || '';
        $('#index_oc_estado_nuevo').val(cur);
        $('#index_oc_estado_obs').val('');
        $('#modalIndexOcCambiarEstado').modal('show');
    });
    if (window.OcCambiarSectorLegajo) {
        window.OcCambiarSectorLegajo.initForm($('#formIndexOcSector'));
    }
    $('.js-oc-index-abrir-sector').on('click', function () {
        var $form = $('#formIndexOcSector');
        $form.attr('action', $(this).data('url'));
        var sid = $(this).data('sector-id');
        if (sid) {
            $('#index_oc_sector_nuevo').val(String(sid));
        }
        $('#index_oc_sector_obs').val('');
        $('#index_oc_sector_leyenda').val('');
        $form.find('input[type=file]').val('');
        var ocId = $(this).data('ordencompra-id') || '';
        if (window.OcCambiarSectorLegajo) {
            window.OcCambiarSectorLegajo.setOrdencompraId($form, ocId);
        }
        $('#modalIndexOcCambiarSector').modal('show');
    });
});
</script>
@endsection

@php
    use App\Support\Compras\OrdencompraListadoFiltros;
    use App\Support\Compras\OrdencompraListadoColumnas;
    use App\Support\Listado\ListadoOrdenamientoSupport;
    use App\Support\Listado\QueryRetornoListado;
    use App\Support\Configuracion\EntornoEmpresaSupport;

    $columnasVisibles = $columnasVisibles ?? [];
    $empresaScope = $filtros['empresa_scope'] ?? 'una';
    $empresaActual = (int) ($filtros['empresa_id'] ?? 0);
    $salaScope = $filtros['sala_scope'] ?? 'todas';
    $salaActual = (int) ($filtros['sala_id'] ?? 0);
    $presupuestoActivo = (string) ($filtros['presupuesto'] ?? '');
    $estadoActual = (string) ($filtros['estado'] ?? '');
    $limpiarQ = OrdencompraListadoFiltros::paraQueryStringExternos($filtros ?? []);
    $limpiarQ['filtro_limpiar'] = 1;
    $limpiarUrl = route('consultar_ordencompra', $limpiarQ);
    $retornoListadoQuery = QueryRetornoListado::retornoLinksDesdeFiltrosQuery($filtrosQuery ?? []);
    $camposOrdenablesThead = OrdencompraListadoFiltros::camposOrdenables();
    $ordenActualThead = ListadoOrdenamientoSupport::normalizar($filtros['sort'] ?? [], $camposOrdenablesThead);
    $qsQuitarOrden = $filtrosQuery ?? [];
    unset($qsQuitarOrden['sort']);
    $qsQuitarOrden['quitar_orden'] = 1;
    $urlQuitarOrden = route('consultar_ordencompra', $qsQuitarOrden);
@endphp

@section('contenido')
@include('includes.contable.modalconsultacuentacontable')
@include('compras.partials.documentos_relacionados_circuito_modal')
@include('compras.ordencompra.partials.modal_enviar_proveedor')
@include('compras.ordencompra.partials.modal_asignar_factura_legajo')

<div class="modal fade" id="modalIndexOcCambiarEstado" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form method="POST" id="formIndexOcEstado" action="">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Cambiar estado</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="index_oc_estado_nuevo">Nuevo estado</label>
                        <select name="estado" id="index_oc_estado_nuevo" class="form-control" required>
                            @foreach ($estados as $est)
                                <option value="{{ $est }}">{{ $est }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="index_oc_estado_obs">Observación</label>
                        <textarea name="observacion" id="index_oc_estado_obs" class="form-control" rows="2" maxlength="2000"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalIndexOcCambiarSector" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form method="POST" id="formIndexOcSector" action="" enctype="multipart/form-data"
                  data-sector-gastronomia-id="{{ (int) \App\Support\Compras\OrdencompraLegajoGastronomiaSupport::sectorGastronomiaId() }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Cambiar sector de legajo</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="index_oc_sector_nuevo">Sector</label>
                        <select name="sector_legajocompra_id" id="index_oc_sector_nuevo" class="form-control" required>
                            @foreach ($sectores as $sec)
                                <option value="{{ $sec->id }}">{{ $sec->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    @include('compras.ordencompra.partials.bloque_factura_legajo_sector', ['prefix' => 'index_oc'])
                    <div class="form-group">
                        <label for="index_oc_sector_obs">Observaci&oacute;n / comentario al &aacute;rbol</label>
                        <input type="text" name="observacion" id="index_oc_sector_obs" class="form-control" maxlength="255" placeholder="Motivo del traslado (tambi&eacute;n va al &aacute;rbol si aplica)">
                    </div>
                    <div class="form-group">
                        <label for="index_oc_sector_leyenda">Leyenda</label>
                        <textarea name="leyenda" id="index_oc_sector_leyenda" class="form-control" rows="2" maxlength="2000"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Registrar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="row oc-ui erp-ws-host lw-workbench">
    <div class="col-lg-12">
        @include('includes.mensaje')

        <div class="oc-header">
            <h1><i class="fa fa-shopping-cart"></i> Órdenes de compra</h1>
            <div class="oc-header-acciones">
                @include('includes.compras.boton-manual')
                @if (can('listar-legajo-compra', false) || can('listar-ordencompra', false))
                    <a href="{{ route('consultar_legajo_compra') }}"
                       class="btn btn-outline-light btn-sm"
                       title="Bandeja de pendientes, estados e histórico de legajos">
                        <i class="fa fa-folder-open"></i> Bandeja de legajos
                    </a>
                @endif
                @if (can('listar-kpi-compras', false))
                    <a href="{{ route('consultar_kpi_compras', ['origen' => 'ordencompra']) }}"
                       class="btn btn-outline-success btn-sm"
                       title="Tablero de KPIs de proceso y productividad">
                        <i class="fas fa-chart-line"></i> KPIs
                    </a>
                @endif
                @if (can('crear-ordencompra', false))
                    <a href="{{ route('crear_ordencompra', $retornoListadoQuery) }}" class="btn btn-light btn-sm">
                        <i class="fa fa-fw fa-plus-circle"></i> Nueva orden
                    </a>
                @endif
            </div>
        </div>

        <div class="oc-panel">
            <p class="oc-intro">
                Circuito de compra: de la requisición a la recepción, el legajo y el pago. Filtre por estado, empresa o consulta avanzada; abra la OC en solapa sin salir del listado.
            </p>
            @if (!empty($alcanceSector))
                <p class="oc-alcance"><i class="fa fa-lock"></i> {{ $alcanceSector }}</p>
            @endif

            @include('compras.ordencompra.partials.resumen_index')
            @include('compras.ordencompra.partials.segmentos_estado')

            @if (! ($workbenchListo ?? false))
                <div class="alert alert-warning lw-aviso-migracion mb-0 mx-3 mt-2">
                    <strong>Migración pendiente.</strong>
                    Para vistas y configuración de grilla hace falta la tabla <code>listado_vista</code>.
                </div>
            @endif

            <form method="get" action="{{ route('consultar_ordencompra') }}" id="form-filtros-ordencompra" class="mb-0">
                <input type="hidden" name="filtro_busqueda_rapida" id="filtro_busqueda_rapida" value="">
                <input type="hidden" name="filtro_modo" id="filtro_modo" value="{{ $filtros['modo'] ?? 'todos' }}">
                <input type="hidden" name="filtro_valor" id="filtro_valor" value="{{ $filtros['valor'] ?? '' }}">
                <input type="hidden" name="columnas" id="lw_columnas_csv" value="{{ implode(',', $columnasVisibles) }}">
                @if ($empresaScope === 'todas')
                    <input type="hidden" name="empresa_todas" value="1">
                @elseif ($empresaActual > 0)
                    <input type="hidden" name="empresa_id" value="{{ $empresaActual }}">
                @endif
                @if (EntornoEmpresaSupport::esCrown())
                    @if ($salaScope === 'todas')
                        <input type="hidden" name="sala_todas" value="1">
                    @elseif ($salaActual > 0)
                        <input type="hidden" name="sala_id" value="{{ $salaActual }}">
                    @endif
                @endif
                @if ($presupuestoActivo !== '')
                    <input type="hidden" name="presupuesto" value="{{ $presupuestoActivo }}">
                @endif
                @if ($estadoActual !== '')
                    <input type="hidden" name="estado" value="{{ $estadoActual }}">
                @endif
                @if ($vistaActiva ?? null)
                    <input type="hidden" name="vista_id" value="{{ $vistaActiva->id }}">
                @elseif (! empty($filtros['vista_estandar']))
                    <input type="hidden" name="vista_estandar" value="1">
                @endif

                <div class="lw-toolbar">
                    <div class="lw-toolbar-left">
                        <select id="lw-vista-select" class="form-control form-control-sm lw-vista-select"
                                data-base-url="{{ route('consultar_ordencompra') }}"
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
                            <i class="fa fa-font"></i> Defaults
                        </button>
                    </div>
                    <div class="lw-toolbar-right">
                        <input type="search" id="lw-search-rapida" class="form-control form-control-sm lw-search-rapida"
                               value="{{ ($filtros['modo'] ?? '') !== 'qbe' ? ($filtros['valor'] ?? '') : '' }}"
                               placeholder="Nº OC, proveedor, solicitante…"
                               autocomplete="off">
                        <button type="button" id="btn-lw-buscar-rapida" class="btn btn-sm btn-primary">
                            <i class="fa fa-search"></i>
                        </button>
                        @if (OrdencompraListadoFiltros::tieneCriteriosTexto($filtros ?? []))
                            <a href="{{ $limpiarUrl }}" class="btn btn-sm btn-outline-warning">
                                <i class="fa fa-eraser"></i> Limpiar
                            </a>
                        @endif
                        @if (($ordenActualThead ?? []) !== [])
                            <a href="{{ $urlQuitarOrden }}" class="btn btn-sm btn-outline-secondary" title="Vuelve al orden por fecha, más reciente primero">
                                <i class="fa fa-undo"></i> Orden estándar
                            </a>
                        @endif
                    </div>
                </div>

                @include('compras.ordencompra.partials.filtros_externos', [
                    'exportRuta' => 'listar_ordencompra',
                    'exportQueryparams' => $filtrosQuery ?? [],
                    'sala_query' => $sala_query ?? collect(),
                ])
                @include('compras.ordencompra.partials.workbench_qbe')
                @php
                    $ejesVisual = OrdencompraListadoFiltros::camposOrdenables();
                    $medidasVisual = \App\Support\Listado\ListadoVisualSupport::medidasDisponibles($ejesVisual);
                    $lienzoVisual = true;
                @endphp
                @include('includes.listado.workbench_visual')
            </form>

            @include('includes.listado.workbench_grafico', [
                'recursoVisual' => OrdencompraListadoColumnas::RECURSO,
                'rutaListadoVisual' => 'consultar_ordencompra',
            ])

            <div class="px-3 pt-2">
                @include('includes.listado.workbench_cortes', ['cortes' => $cortes ?? []])
            </div>

            <div class="tabla-ancha-grilla oc-grilla-ancha" data-tabla-ancha>
                <div class="tabla-ancha-scroll-top" hidden>
                    <div class="tabla-ancha-scroll-top-inner"></div>
                </div>
                <div class="tabla-ancha-wrap oc-grilla-wrap p-0">
                    <table class="table table-bordered table-hover oc-grilla tabla-ancha mb-0" id="tabla-paginada">
                        <thead>
                            <tr>
                                @foreach ($columnasVisibles as $keyColumna)
                                    @include('compras.ordencompra.partials.workbench_th', ['key' => $keyColumna])
                                @endforeach
                                <th class="oc-col-acciones width40 lw-col-acciones lw-col-fija" data-orderable="false">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($ordencompra as $row)
                                @php
                                    $esSuspendidaFila = ($row->estadoordencompra ?? '') === \App\Support\Compras\OrdencompraEstados::SUSPENDIDA;
                                    $tonoFila = \App\Support\Listado\ListadoVisualSupport::tonoFila(
                                        $row,
                                        $filtros['formato'] ?? [],
                                        static fn ($r, $k) => OrdencompraListadoColumnas::valorCelda($r, $k)
                                    );
                                @endphp
                                <tr data-ws-id="{{ $row->id }}"
                                    @class([
                                        'oc-fila-suspendida' => $esSuspendidaFila,
                                        'lw-tono-danger' => $tonoFila === 'danger',
                                        'lw-tono-warning' => $tonoFila === 'warning',
                                        'lw-tono-success' => $tonoFila === 'success',
                                    ])>
                                    @foreach ($columnasVisibles as $keyColumna)
                                        @include('compras.ordencompra.partials.workbench_celda', [
                                            'key' => $keyColumna,
                                            'row' => $row,
                                        ])
                                    @endforeach
                                    <td class="oc-col-acciones width40 lw-col-acciones">
                                        @include('compras.ordencompra.partials.acciones_grilla')
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ count($columnasVisibles) + 1 }}" class="oc-vacio">
                                        <i class="fa fa-inbox"></i>
                                        <div class="oc-vacio-titulo">No hay órdenes para este filtro</div>
                                        Ajuste el estado, la empresa o la consulta avanzada.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if (method_exists($ordencompra, 'links'))
            <div class="oc-footer">
                {{ $ordencompra->appends(array_merge($filtrosQuery ?? [], request()->only(['origen', 'vista'])))->links() }}
            </div>
            @endif
        </div>
    </div>
</div>

@include('includes.listado.workbench_columnas_dnd', [
    'rutaGuardarColumnasDnd' => 'guardar_columnas_listado_ordencompra',
    'paraQueryStringDnd' => [OrdencompraListadoFiltros::class, 'paraQueryString'],
    'filtrosDnd' => $filtros ?? [],
])
@include('compras.ordencompra.partials.workbench_modales')
@endsection
