@extends("theme.$theme.layout")
@section('titulo')
    Ingresos y Egresos de Caja
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
    $ieWorkbenchJs = public_path('assets/pages/scripts/caja/ingresoegreso/workbench.js');
@endphp
<script src="{{ asset('assets/pages/scripts/listado/workbench-qbe-grupos.js') }}?v={{ file_exists($qbeGruposJs) ? filemtime($qbeGruposJs) : time() }}"></script>
<script src="{{ asset('assets/pages/scripts/listado/workbench-orden.js') }}?v={{ file_exists($ordenJs) ? filemtime($ordenJs) : time() }}"></script>
<script src="{{ asset('assets/pages/scripts/listado/workbench-agrupar.js') }}?v={{ file_exists($agruparJs) ? filemtime($agruparJs) : time() }}"></script>
<script src="{{ asset('assets/pages/scripts/listado/workbench-disenador-preview.js') }}?v={{ file_exists($disenadorJs) ? filemtime($disenadorJs) : time() }}"></script>
<script src="{{ asset('assets/pages/scripts/listado/workbench-vista-guardar.js') }}?v={{ file_exists($vistaGuardarJs) ? filemtime($vistaGuardarJs) : time() }}"></script>
<script src="{{ asset('assets/pages/scripts/caja/ingresoegreso/workbench.js') }}?v={{ file_exists($ieWorkbenchJs) ? filemtime($ieWorkbenchJs) : time() }}"></script>
<script src="{{ asset('assets/pages/scripts/caja/ingresoegreso/anular_revertir.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/caja/ingresoegreso/anular_revertir.js')) ?: time() }}" type="text/javascript"></script>
@endsection

@php
    use App\Support\Caja\IngresoEgresoListadoFiltros;
    use App\Support\Caja\IngresoEgresoListadoMontoSupport;

    $columnasVisibles = $columnasVisibles ?? [];
    $retornoListadoQuery = \App\Support\Listado\QueryRetornoListado::retornoLinksDesdeFiltrosQuery($filtrosQuery ?? []);
    $limpiarQ = IngresoEgresoListadoFiltros::paraQueryStringEmpresa($filtros ?? []);
    if (($filtros['tipos'] ?? []) !== []) {
        $limpiarQ['tipos'] = array_values($filtros['tipos']);
    }
    if (($filtros['periodo'] ?? '') !== '') {
        $limpiarQ['filtro_periodo'] = $filtros['periodo'];
    }
    if ($vistaActiva ?? null) {
        $limpiarQ['vista_id'] = $vistaActiva->id;
    }
    $limpiarQ['filtro_limpiar'] = 1;
    $limpiarUrl = route('ingresoegreso', $limpiarQ);
    $totalesListado = $totalesListado ?? ['cantidad' => 0, 'ingresos' => 0, 'egresos' => 0];
    $qbeAbierto = \App\Support\Listado\ListadoQbeSupport::tieneCriterios($filtros['qbe'] ?? []);
    $empresaScope = $filtros['empresa_scope'] ?? 'una';
    $empresaActual = (int) ($filtros['empresa_id'] ?? 0);
@endphp

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info lw-workbench shadow-sm">
            <div class="card-header lw-header d-flex flex-wrap align-items-center justify-content-between">
                <h3 class="card-title mb-0">Ingresos y Egresos de Caja</h3>
                <div class="card-tools ml-auto d-flex flex-wrap align-items-center justify-content-end" style="gap:.4rem;">
                    @if (can('crear-ingresos-egresos-caja', false))
                        <a href="{{ route('crear_ingresoegreso', $retornoListadoQuery) }}" class="btn btn-light btn-sm">
                            <i class="fa fa-plus"></i> Nuevo
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
            <form method="get" action="{{ route('ingresoegreso') }}" id="form-filtros-ingresoegreso" class="mb-0">
                <input type="hidden" name="filtro_busqueda_rapida" id="filtro_busqueda_rapida" value="">
                <input type="hidden" name="filtro_modo" id="filtro_modo" value="{{ $filtros['modo'] ?? 'todos' }}">
                <input type="hidden" name="filtro_valor" id="filtro_valor" value="{{ $filtros['valor'] ?? '' }}">
                <input type="hidden" name="columnas" id="lw_columnas_csv" value="{{ implode(',', $columnasVisibles) }}">
                @if ($empresaScope === 'todas')
                    <input type="hidden" name="empresa_todas" value="1">
                @elseif ($empresaActual > 0)
                    <input type="hidden" name="empresa_id" value="{{ $empresaActual }}">
                @endif
                @foreach (($filtros['tipos'] ?? []) as $tipoOculto)
                    <input type="hidden" name="tipos[]" value="{{ (int) $tipoOculto }}">
                @endforeach
                @if (($filtros['periodo'] ?? '') !== '')
                    <input type="hidden" name="filtro_periodo" value="{{ $filtros['periodo'] }}">
                @endif
                @if (($filtros['fecha_desde'] ?? '') !== '')
                    <input type="hidden" name="fecha_desde" value="{{ $filtros['fecha_desde'] }}">
                @endif
                @if (($filtros['fecha_hasta'] ?? '') !== '')
                    <input type="hidden" name="fecha_hasta" value="{{ $filtros['fecha_hasta'] }}">
                @endif
                @if (! empty($filtros['solicitudpago_id']))
                    <input type="hidden" name="solicitudpago_id" value="{{ (int) $filtros['solicitudpago_id'] }}">
                @endif
                @if ($vistaActiva ?? null)
                    <input type="hidden" name="vista_id" value="{{ $vistaActiva->id }}">
                @endif
                <div class="lw-toolbar">
                    <div class="lw-toolbar-left">
                        <select id="lw-vista-select" class="form-control form-control-sm lw-vista-select"
                                data-base-url="{{ route('ingresoegreso') }}"
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
                        <button type="button" class="btn btn-sm btn-outline-info {{ ($qbeAbierto ?? false) ? '' : 'collapsed' }}" data-toggle="collapse" data-target="#lw-qbe-panel" aria-expanded="{{ ($qbeAbierto ?? false) ? 'true' : 'false' }}" aria-controls="lw-qbe-panel">
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
                               placeholder="Texto o número"
                               autocomplete="off">
                        <button type="button" id="btn-lw-buscar-rapida" class="btn btn-sm btn-primary">
                            <i class="fa fa-search"></i>
                        </button>
                        @if (IngresoEgresoListadoFiltros::tieneCriteriosTexto($filtros ?? []))
                            <a href="{{ $limpiarUrl }}" class="btn btn-sm btn-outline-warning">
                                <i class="fa fa-eraser"></i> Limpiar
                            </a>
                        @endif
                    </div>
                </div>
                @include('caja.ingresoegreso.partials.filtros_externos')
                @include('caja.ingresoegreso.partials.workbench_qbe')
            </form>
            @if (! empty($filtros['solicitudpago_id']))
                <div class="px-3 py-2 border-bottom bg-light small">
                    <i class="fa fa-link text-primary"></i>
                    Filtrado por solicitud de pago id <strong>{{ (int) $filtros['solicitudpago_id'] }}</strong>.
                    <a href="{{ route('editar_solicitudpago', ['id' => (int) $filtros['solicitudpago_id'], 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}"
                       class="text-primary ml-1" target="_blank" rel="noopener">Abrir SP</a>
                    <a href="{{ route('ingresoegreso', \App\Support\Caja\IngresoEgresoListadoFiltros::paraQueryStringEmpresa($filtros ?? [])) }}"
                       class="ml-2">Quitar filtro SP</a>
                </div>
            @endif
            @if (! empty($alcance_centro_costo))
                <div class="px-3 py-2 border-bottom bg-white text-muted small">
                    <i class="fa fa-filter"></i>
                    Alcance del listado:
                    <strong>{{ $alcance_centro_costo }}</strong>
                    <span class="text-muted">· Sin cobranzas POS (módulo Cobranza)</span>
                </div>
            @else
                <div class="px-3 py-2 border-bottom bg-white text-muted small">
                    <i class="fa fa-info-circle"></i>
                    Listado de ingresos/egresos de caja (OPP, remesas, transferencias, etc.).
                    Las cobranzas POS (gastronomía, estacionamiento) se consultan en el módulo Cobranza.
                </div>
            @endif
            <div class="px-3 py-2 border-bottom bg-white small d-flex flex-wrap" style="gap:1rem;">
                <span><strong>{{ number_format((int) $totalesListado['cantidad'], 0, ',', '.') }}</strong> movimientos</span>
                <span>Ingresos <strong>{{ number_format((float) $totalesListado['ingresos'], 2, ',', '.') }}</strong></span>
                <span>Egresos <strong>{{ number_format((float) $totalesListado['egresos'], 2, ',', '.') }}</strong></span>
                <span class="text-muted">Totales del filtro completo, en pesos.</span>
            </div>
            <div class="px-3 pt-2">
                @include('includes.listado.workbench_cortes', ['cortes' => $cortes ?? []])
            </div>
            <div class="card-body py-2 border-bottom bg-white">
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'lista_ingresoegreso',
                    'queryparams' => $filtrosQuery ?? [],
                ])
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-striped table-bordered table-hover mb-0" id="tabla-paginada">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            @foreach ($columnasVisibles as $keyColumna)
                                @include('caja.ingresoegreso.partials.workbench_th', ['key' => $keyColumna])
                            @endforeach
                            <th class="width80" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($caja_movimiento as $data)
                        @php
                            $ieMonto = $data->_ie_resumen ?? IngresoEgresoListadoMontoSupport::resumen($data);
                        @endphp
                        <tr>
                            @foreach ($columnasVisibles as $keyColumna)
                                @include('caja.ingresoegreso.partials.workbench_celda', [
                                    'key' => $keyColumna,
                                    'data' => $data,
                                    'ieMonto' => $ieMonto,
                                    'retornoListadoQuery' => $retornoListadoQuery,
                                ])
                            @endforeach
                            <td class="text-nowrap">
                                @if (can('listar-ingresos-egresos-caja', false))
                                    <a href="{{ route('imprimir_ingresoegreso', $data->id) }}"
                                       class="btn-accion-tabla tooltipsC"
                                       title="Emitir comprobante / orden de pago"
                                       target="_blank" rel="noopener">
                                        <i class="fa fa-print"></i>
                                    </a>
                                @endif
                                @if (can('editar-ingresos-egresos-caja', false))
                                    @php
                                        $ieSoloLectura = ! empty($data->caja_movimiento_origen_id)
                                            || ! empty($data->caja_movimiento_revertido_por_id);
                                    @endphp
                                    <a href="{{ route('editar_ingresoegreso', ['id' => $data->id, 'origen' => 'ingresoegreso'] + $retornoListadoQuery) }}"
                                       class="btn-accion-tabla tooltipsC"
                                       title="{{ $ieSoloLectura ? 'Consultar (solo lectura)' : 'Editar este registro' }}">
                                        <i class="fa {{ $ieSoloLectura ? 'fa-eye' : 'fa-edit' }}"></i>
                                    </a>
                                @endif
                                @if (
                                    can('anular-ingresos-egresos-caja', false)
                                    && empty($data->caja_movimiento_origen_id)
                                    && empty($data->caja_movimiento_revertido_por_id)
                                )
                                    <form action="{{ route('anular_fisicamente_ingresoegreso', ['id' => $data->id]) }}"
                                          class="d-inline form-anular-fisico-ie" method="POST">
                                        @csrf
                                        <button type="submit" class="btn-accion-tabla tooltipsC" title="Anular físicamente (borra OP y reabre SP)">
                                            <i class="fa fa-ban text-danger"></i>
                                        </button>
                                    </form>
                                @endif
                                @if (
                                    can('revertir-ingresos-egresos-caja', false)
                                    && empty($data->caja_movimiento_origen_id)
                                    && empty($data->caja_movimiento_revertido_por_id)
                                )
                                    <form action="{{ route('revertir_ingresoegreso_id', ['id' => $data->id]) }}"
                                          class="d-inline form-revertir-ie" method="POST">
                                        @csrf
                                        <input type="hidden" name="id" value="{{ $data->id }}">
                                        <button type="submit" class="btn-accion-tabla tooltipsC" title="Revertir (compensatorio + asiento + Anita)">
                                            <i class="fa fa-undo text-warning"></i>
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="{{ count($columnasVisibles) + 1 }}" class="text-center text-muted py-4">
                                No hay movimientos con los filtros aplicados.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
{{ $caja_movimiento->appends($filtrosQuery ?? [])->links() }}
@include('caja.ingresoegreso.partials.workbench_modales')
@endsection
