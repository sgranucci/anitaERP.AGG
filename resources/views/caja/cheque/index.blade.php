@extends("theme.$theme.layout")
@section('titulo')
    Cheques
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
    $chequeWorkbenchJs = public_path('assets/pages/scripts/caja/cheque/workbench.js');
@endphp
<script src="{{ asset('assets/pages/scripts/listado/workbench-qbe-grupos.js') }}?v={{ file_exists($qbeGruposJs) ? filemtime($qbeGruposJs) : time() }}"></script>
<script src="{{ asset('assets/pages/scripts/listado/workbench-orden.js') }}?v={{ file_exists($ordenJs) ? filemtime($ordenJs) : time() }}"></script>
<script src="{{ asset('assets/pages/scripts/listado/workbench-agrupar.js') }}?v={{ file_exists($agruparJs) ? filemtime($agruparJs) : time() }}"></script>
<script src="{{ asset('assets/pages/scripts/listado/workbench-disenador-preview.js') }}?v={{ file_exists($disenadorJs) ? filemtime($disenadorJs) : time() }}"></script>
<script src="{{ asset('assets/pages/scripts/listado/workbench-vista-guardar.js') }}?v={{ file_exists($vistaGuardarJs) ? filemtime($vistaGuardarJs) : time() }}"></script>
<script src="{{ asset('assets/pages/scripts/caja/cheque/workbench.js') }}?v={{ file_exists($chequeWorkbenchJs) ? filemtime($chequeWorkbenchJs) : time() }}"></script>
@if (($graficoCheque['total'] ?? 0) > 0)
<script src="{{ asset('assets/lte/plugins/chart.js/Chart.min.js') }}"></script>
<script>
(function () {
    var datos = @json($graficoCheque ?? []);
    var grafico = null;
    function dibujar() {
        var canvas = document.getElementById('cheque-grafico-estado');
        if (!canvas || typeof Chart === 'undefined' || !datos.labels || !datos.labels.length) {
            return;
        }
        if (!grafico) {
            grafico = new Chart(canvas.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: datos.labels,
                    datasets: [{
                        label: 'Monto',
                        data: datos.montos,
                        backgroundColor: '#85C1E9',
                        borderColor: '#2471A3',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    legend: { display: false },
                    tooltips: {
                        callbacks: {
                            label: function (item) {
                                var i = item.index;
                                var monto = datos.montos[i] || 0;
                                var cant = datos.cantidades[i] || 0;
                                return monto.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
                                    + ' · ' + cant + ' cheques';
                            }
                        }
                    },
                    scales: {
                        yAxes: [{ ticks: { beginAtZero: true } }],
                        xAxes: [{ ticks: { autoSkip: false, maxRotation: 40, minRotation: 0 } }]
                    }
                }
            });
            return;
        }
        grafico.resize();
    }
    var panel = document.getElementById('cheque-grafico-body');
    if (panel && window.jQuery) {
        window.jQuery(panel).on('shown.bs.collapse', dibujar);
    }
})();
</script>
@endif
@if ($puede_nd_cheque ?? false)
<script>
window.chequeRechazoNdUrls = {
    datos: @json(url('caja/cheque/:id/rechazo-nd')),
    emitir: @json(url('caja/cheque/:id/rechazar-nd'))
};
</script>
<script src="{{asset("assets/pages/scripts/caja/cheque/rechazo_nd.js")}}" type="text/javascript"></script>
@endif
@if ($puede_depositar_cheque ?? false)
<script>
window.chequeDepositoUrls = {
    depositar: @json(url('caja/cheque/:id/depositar')),
    depositarMasivo: @json(route('depositar_masivo_cheque'))
};
</script>
<script src="{{ asset('assets/pages/scripts/caja/cuentacaja/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/caja/cuentacaja/consulta.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/caja/cheque/deposito.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/caja/cheque/deposito.js')) ?: time() }}" type="text/javascript"></script>
@endif
@if ($puede_caucionar_cheque ?? false)
<script>
window.chequeCaucionUrls = {
    caucionar: @json(url('caja/cheque/:id/caucionar')),
    caucionarMasivo: @json(route('caucionar_masivo_cheque')),
    liberar: @json(url('caja/cheque/:id/liberar-caucion'))
};
</script>
<script src="{{asset("assets/pages/scripts/caja/cheque/caucion.js")}}" type="text/javascript"></script>
@endif
@endsection

<?php use App\Helpers\biblioteca;
use App\Support\Caja\ChequeListadoFiltros; ?>

@section('contenido')
@php
    $retornoListadoQuery = \App\Support\Listado\QueryRetornoListado::retornoLinksDesdeFiltrosQuery($filtrosQuery ?? []);
    $limpiarUrl = route('cheque', ChequeListadoFiltros::paraQueryStringExternos($filtros ?? []));
    $ordenActual = $filtros['orden'] ?? 'fechapago';
    $ordenDir = $filtros['orden_dir'] ?? 'desc';
    $urlOrden = function (string $col) use ($filtrosQuery, $ordenActual, $ordenDir) {
        $q = $filtrosQuery ?? [];
        unset($q['sort'], $q['group']);
        $q['orden'] = $col;
        if ($ordenActual === $col) {
            $q['orden_dir'] = $ordenDir === 'asc' ? 'desc' : 'asc';
        } else {
            $q['orden_dir'] = in_array($col, ['numerocheque'], true) ? 'asc' : 'desc';
        }

        return route('cheque', $q);
    };
    $marcaOrden = function (string $col) use ($ordenActual, $ordenDir) {
        if ($ordenActual !== $col) {
            return '';
        }

        return $ordenDir === 'asc' ? ' ↑' : ' ↓';
    };
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info lw-workbench shadow-sm">
            <div class="card-header lw-header d-flex flex-wrap align-items-center justify-content-between">
                <h3 class="card-title mb-0">
                    <i class="fa fa-money mr-1"></i> Cheques
                    <small class="ml-2" style="opacity:.85;font-weight:400;">Workbench · consulta multi-campo</small>
                </h3>
                <div class="card-tools ml-auto d-flex flex-wrap align-items-center justify-content-end" style="gap:.4rem;">
                    @if (can('crear-cheque', false))
                    <a href="{{ route('importar_cheque') }}" class="btn btn-outline-light btn-sm" title="Ingreso masivo">
                        <i class="fa fa-upload"></i> Importar
                    </a>
                    @endif
                    <a href="{{ route('reporte_cheque') }}" class="btn btn-outline-light btn-sm" title="Emitidos y recibidos por separado">
                        <i class="fa fa-list-alt"></i> Reporte
                    </a>
                    <a href="{{ route('aging_cheque_cartera') }}" class="btn btn-outline-light btn-sm" title="Aging cartera">
                        <i class="fa fa-hourglass-half"></i> Aging
                    </a>
                    <a href="{{ route('historial_deposito_cheque') }}" class="btn btn-outline-light btn-sm" title="Historial boletas de depósito">
                        <i class="fa fa-university"></i> Depósitos
                    </a>
                    <a href="{{ route('conciliacion_deposito_cheque') }}" class="btn btn-outline-light btn-sm" title="Conciliación depósitos">
                        <i class="fa fa-balance-scale"></i> Conciliación
                    </a>
                    <a href="{{ route('cashflow_cheque') }}" class="btn btn-outline-light btn-sm" title="Cashflow semanal">
                        <i class="fa fa-calendar"></i> Cashflow
                    </a>
                    <a href="{{ route('echeq_cheque') }}" class="btn btn-outline-light btn-sm" title="eCheq">
                        <i class="fa fa-mobile"></i> eCheq
                    </a>
                    @if (can('crear-cheque', false))
                    <a href="{{ route('crear_cheque', $retornoListadoQuery) }}" class="btn btn-light btn-sm">
                        <i class="fa fa-plus"></i> Nuevo cheque
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
            <form method="get" action="{{ route('cheque') }}" id="form-filtros-cheque" class="mb-0">
                <input type="hidden" name="filtro_busqueda_rapida" id="filtro_busqueda_rapida" value="">
                <input type="hidden" name="filtro_modo" id="filtro_modo" value="{{ $filtros['modo'] ?? 'todos' }}">
                <input type="hidden" name="filtro_valor" id="filtro_valor" value="{{ $filtros['valor'] ?? '' }}">
                <input type="hidden" name="columnas" id="lw_columnas_csv" value="{{ implode(',', $columnasVisibles ?? []) }}">
                @if (! empty($filtros['cartera']))
                    <input type="hidden" name="cartera" value="1">
                @endif
                @if (! empty($filtros['para_depositar']))
                    <input type="hidden" name="para_depositar" value="1">
                    <input type="hidden" name="para_depositar_hasta" value="{{ $filtros['para_depositar_hasta'] ?? '' }}">
                @endif
                @if (($filtros['origen'] ?? '') !== '')
                    <input type="hidden" name="origen" value="{{ $filtros['origen'] }}">
                @endif
                @if (array_key_exists('estado', $filtros) && $filtros['estado'] !== null && $filtros['estado'] !== '')
                    <input type="hidden" name="estado" value="{{ $filtros['estado'] }}">
                @endif
                @if (($filtros['empresa_scope'] ?? '') === 'todas')
                    <input type="hidden" name="empresa_todas" value="1">
                @elseif (! empty($filtros['empresa_id']))
                    <input type="hidden" name="empresa_id" value="{{ $filtros['empresa_id'] }}">
                @endif
                <input type="hidden" name="orden" value="{{ $filtros['orden'] ?? 'fechapago' }}">
                <input type="hidden" name="orden_dir" value="{{ $filtros['orden_dir'] ?? 'desc' }}">
                @if ($vistaActiva ?? null)
                    <input type="hidden" name="vista_id" value="{{ $vistaActiva->id }}">
                @endif
                <div class="lw-toolbar">
                    <div class="lw-toolbar-left">
                        <select id="lw-vista-select" class="form-control form-control-sm lw-vista-select"
                                data-base-url="{{ route('cheque') }}"
                                title="Vistas guardadas"
                                @if (! ($workbenchListo ?? false)) disabled @endif>
                            <option value="">Vista estándar</option>
                            @foreach (($vistasListado ?? []) as $vista)
                                <option value="{{ $vista->id }}" @if (($vistaActiva ?? null) && (int) $vistaActiva->id === (int) $vista->id) selected @endif>
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
                               placeholder="Texto o número"
                               autocomplete="off">
                        <button type="button" id="btn-lw-buscar-rapida" class="btn btn-sm btn-primary">
                            <i class="fa fa-search"></i>
                        </button>
                        @if (ChequeListadoFiltros::tieneCriteriosTexto($filtros ?? []))
                            <a href="{{ $limpiarUrl }}" class="btn btn-sm btn-outline-warning">
                                <i class="fa fa-eraser"></i> Limpiar
                            </a>
                        @endif
                    </div>
                </div>
                @include('caja.cheque.partials.workbench_qbe')
            </form>
            @include('caja.cheque.partials.filtros_externos')
            <div class="px-3 pt-2">
                @include('includes.listado.workbench_cortes', ['cortes' => $cortes ?? []])
            </div>
            <div class="px-3 pt-2 pb-1">
                <div class="card card-outline card-info mb-0 lw-cortes">
                    <div class="card-header py-2 px-3 d-flex flex-wrap align-items-center justify-content-between">
                        <button type="button" class="btn btn-sm lw-cortes-toggle lw-grafico-toggle collapsed" id="btn-cheque-grafico"
                                data-toggle="collapse" data-target="#cheque-grafico-body"
                                aria-expanded="false" aria-controls="cheque-grafico-body"
                                title="Mostrar monto por estado">
                            <i class="fa fa-chevron-down lw-cortes-ico lw-cortes-ico-abierto" aria-hidden="true"></i>
                            <i class="fa fa-chevron-right lw-cortes-ico lw-cortes-ico-cerrado" aria-hidden="true"></i>
                            Monto por estado
                        </button>
                        <span class="text-muted small">Universo del filtro, no solo la p&aacute;gina.</span>
                    </div>
                    <div class="collapse" id="cheque-grafico-body">
                        <div class="card-body py-2">
                            @if (($graficoCheque['total'] ?? 0) === 0)
                                <p class="text-muted mb-0">No hay cheques en este filtro para graficar.</p>
                            @else
                                <div style="height:220px;">
                                    <canvas id="cheque-grafico-estado"></canvas>
                                </div>
                                @if (! empty($graficoCheque['truncado']))
                                    <p class="small text-muted mb-0 mt-1">El gr&aacute;fico muestra los grupos m&aacute;s grandes del filtro.</p>
                                @endif
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    var panel = document.getElementById('cheque-grafico-body');
                    var btn = document.getElementById('btn-cheque-grafico');
                    if (!panel || !btn || typeof jQuery === 'undefined') {
                        return;
                    }
                    jQuery(panel).on('shown.bs.collapse hidden.bs.collapse', function (e) {
                        if (e.target !== panel) {
                            return;
                        }
                        var abierto = e.type === 'shown';
                        btn.title = abierto ? 'Ocultar monto por estado' : 'Mostrar monto por estado';
                        btn.setAttribute('aria-expanded', abierto ? 'true' : 'false');
                    });
                });
            </script>
            <div class="card-body table-responsive p-0">
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'lista_cheque',
                    'queryparams' => $filtrosQuery ?? [],
                ])
                <table class="table table-sm table-striped table-bordered table-hover mb-0" id="tabla-paginada">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            @if (($puede_depositar_cheque ?? false) || ($puede_caucionar_cheque ?? false))
                            <th class="text-center" style="width:2.2rem;" data-orderable="false">
                                <input type="checkbox" id="cheque-select-all" title="Seleccionar página" />
                            </th>
                            @endif
                            @foreach ($columnasVisibles as $keyColumna)
                            @include('caja.cheque.partials.workbench_th', ['key' => $keyColumna])
                            @endforeach
                            <th style="width:6rem;" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($datas as $data)
                        @php
                            $origenLabel = collect($origen_enum ?? [])->firstWhere('valor', $data->origen);
                            $estadoLabel = collect($estado_enum ?? [])->firstWhere('valor', $data->estado);
                            $enCartera = ($data->origen ?? '') === 'R'
                                && empty($data->pagoproveedor_id)
                                && in_array((string) ($data->estado ?? ' '), [' ', 'N', ''], true);
                            $puedeRechazarNd = ($puede_nd_cheque ?? false)
                                && ($data->origen ?? '') === 'R'
                                && ! in_array((string) ($data->estado ?? ''), ['R', 'A'], true)
                                && empty($data->venta_nd_id)
                                && ! empty($data->cliente_id);
                            $puedeDepositar = ($puede_depositar_cheque ?? false)
                                && ($data->origen ?? '') === 'R'
                                && empty($data->fecha_deposito)
                                && empty($data->pagoproveedor_id)
                                && ! in_array((string) ($data->estado ?? ''), ['R', 'A', '*'], true)
                                && (trim((string) ($data->nro_caucion ?? '')) === '' || trim((string) ($data->nro_caucion ?? '')) === '0')
                                && (
                                    empty($filtros['para_depositar'])
                                    || (
                                        ! empty($data->fechapago)
                                        && (string) $data->fechapago <= (string) ($filtros['para_depositar_hasta'] ?? date('Y-m-d'))
                                    )
                                );
                            $puedeCaucionar = ($puede_caucionar_cheque ?? false)
                                && ($data->origen ?? '') === 'R'
                                && empty($data->fecha_deposito)
                                && empty($data->pagoproveedor_id)
                                && ! in_array((string) ($data->estado ?? ''), ['R', 'A', '*'], true)
                                && (trim((string) ($data->nro_caucion ?? '')) === '' || trim((string) ($data->nro_caucion ?? '')) === '0');
                            $estaCaucionado = trim((string) ($data->nro_caucion ?? '')) !== ''
                                && trim((string) ($data->nro_caucion ?? '')) !== '0';
                        @endphp
                        <tr>
                            @if (($puede_depositar_cheque ?? false) || ($puede_caucionar_cheque ?? false))
                            <td class="text-center">
                                @if ($puedeDepositar || $puedeCaucionar)
                                    <input type="checkbox" class="cheque-select-row" value="{{ $data->id }}"
                                           data-monto="{{ number_format((float) $data->monto, 2, '.', '') }}"
                                           data-moneda="{{ $data->monedas->abreviatura ?? '$' }}"
                                           data-empresa-id="{{ (int) ($data->empresa_id ?? 0) }}" />
                                @endif
                            </td>
                            @endif
                            @foreach ($columnasVisibles as $keyColumna)
                            @include('caja.cheque.partials.workbench_celda', ['key' => $keyColumna])
                            @endforeach
                            <td>
                       			@if (can('editar-cheque', false))
                                	<a href="{{route('editar_cheque', ['id' => $data->id] + $retornoListadoQuery)}}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                    <i class="fa fa-edit"></i>
                                	</a>
								@endif
                                @if ($puedeDepositar)
                                    <button type="button"
                                            class="btn-accion-tabla tooltipsC btn-deposito-cheque"
                                            title="Depositar"
                                            data-cheque-id="{{ $data->id }}"
                                            data-cheque-ref="{{ $data->numerocheque }} / {{ $data->bancos->nombre ?? '' }}"
                                            data-cheque-monto="{{ number_format((float) $data->monto, 2, '.', '') }}"
                                            data-cheque-moneda="{{ $data->monedas->abreviatura ?? '$' }}"
                                            data-empresa-id="{{ (int) ($data->empresa_id ?? 0) }}">
                                        <i class="fa fa-university text-primary"></i>
                                    </button>
                                @endif
                                @if (! empty($data->fecha_deposito))
                                    <a href="{{ route('comprobante_deposito_cheque', ['ids' => $data->id]) }}"
                                       class="btn-accion-tabla tooltipsC"
                                       title="PDF boleta de depósito"
                                       target="_blank" rel="noopener">
                                        <i class="fa fa-file-pdf-o text-danger"></i>
                                    </a>
                                @endif
                                @if ($puedeCaucionar)
                                    <button type="button"
                                            class="btn-accion-tabla tooltipsC btn-caucion-cheque"
                                            title="Caucionar"
                                            data-cheque-id="{{ $data->id }}"
                                            data-cheque-ref="{{ $data->numerocheque }} / {{ $data->bancos->nombre ?? '' }}">
                                        <i class="fa fa-lock text-warning"></i>
                                    </button>
                                @endif
                                @if ($estaCaucionado && ($puede_caucionar_cheque ?? false) && empty($data->fecha_deposito))
                                    <button type="button"
                                            class="btn-accion-tabla tooltipsC btn-liberar-caucion-cheque"
                                            title="Liberar caución {{ $data->nro_caucion }}"
                                            data-cheque-id="{{ $data->id }}">
                                        <i class="fa fa-unlock text-warning"></i>
                                    </button>
                                @endif
                                @if ($puedeRechazarNd)
                                    <button type="button"
                                            class="btn-accion-tabla tooltipsC btn-rechazo-nd-cheque"
                                            title="Rechazar y emitir ND"
                                            data-cheque-id="{{ $data->id }}">
                                        <i class="fa fa-ban text-danger"></i>
                                    </button>
                                @endif
                       			@if (can('borrar-cheque', false))
                                <form action="{{route('eliminar_cheque', ['id' => $data->id])}}" class="d-inline form-eliminar" method="POST">
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
{{ $datas->appends($filtrosQuery ?? [])->links() }}
@if ($puede_nd_cheque ?? false)
    @include('caja.cheque.modal_rechazo_nd')
@endif
@if ($puede_depositar_cheque ?? false)
    @include('caja.cheque.modal_deposito')
    @include('includes.caja.modalconsultacuentacaja')
@endif
@if ($puede_caucionar_cheque ?? false)
    @include('caja.cheque.modal_caucion')
@endif
@include('caja.cheque.partials.workbench_modales')
@endsection
