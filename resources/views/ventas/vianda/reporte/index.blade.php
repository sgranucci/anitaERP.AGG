@extends("theme.$theme.layout")
@section('titulo')
    Reporte de viandas
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
<script>
(function () {
    var $form = $('#form-reporte-vianda');
    if (!$form.length) {
        return;
    }
    $form.on('click', '.btn-toggle-orden-vianda', function () {
        var $btn = $(this);
        var $input = $($btn.data('input'));
        if (!$input.length) {
            return;
        }
        var porCentrocosto = String($input.val()) !== 'centrocosto';
        $input.val(porCentrocosto ? 'centrocosto' : 'usuario');
        $btn.toggleClass('btn-success', porCentrocosto);
        $btn.toggleClass('btn-outline-secondary', !porCentrocosto);
    });
})();
</script>
@endsection

@section('contenido')
@php
    $estadoActual = $filtros['estado'] ?? 'A';
    $ordenPorCentrocosto = \App\Support\Ventas\Vianda\ViandaConsumoListadoFiltros::normalizarOrden($filtros['orden_por'] ?? 'centrocosto')
        === \App\Support\Ventas\Vianda\ViandaConsumoListadoFiltros::ORDEN_CENTROCOSTO;
    $logosVista = \App\Support\Configuracion\EmpresaLogoArchivo::logosCabeceraDesdeColeccion($filas->getCollection());
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Viandas del día / período</h3>
                <div class="card-tools">
                    <a href="{{ route('consultar_reporte_vianda_gastronomia') }}" class="btn btn-outline-secondary btn-sm" title="Limpiar filtros">
                        <i class="fa fa-eraser"></i> Limpiar
                    </a>
                </div>
            </div>

            <form method="get" action="{{ route('consultar_reporte_vianda_gastronomia') }}" id="form-reporte-vianda" class="mb-0">
                <div class="card-body pb-2">
                    <div class="form-group row">
                        <label for="fecha_desde" class="col-lg-2 control-label text-right pr-2">Desde</label>
                        <div class="col-lg-3">
                            <input type="date" name="fecha_desde" id="fecha_desde" class="form-control"
                                value="{{ $filtros['fecha_desde'] ?? date('Y-m-d') }}">
                        </div>
                        <label for="fecha_hasta" class="col-lg-2 control-label text-right pr-2">Hasta</label>
                        <div class="col-lg-3">
                            <input type="date" name="fecha_hasta" id="fecha_hasta" class="form-control"
                                value="{{ $filtros['fecha_hasta'] ?? date('Y-m-d') }}">
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="empresa_id" class="col-lg-2 control-label text-right pr-2">Empresa</label>
                        <div class="col-lg-3">
                            <select name="empresa_id" id="empresa_id" class="form-control">
                                <option value="">Todas</option>
                                @foreach ($empresa_query as $empresa)
                                    <option value="{{ $empresa->id }}" @selected((int) ($filtros['empresa_id'] ?? 0) === (int) $empresa->id)>
                                        {{ $empresa->nombre }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <label for="centrocosto_id" class="col-lg-2 control-label text-right pr-2">Centro de costo</label>
                        <div class="col-lg-3">
                            <select name="centrocosto_id" id="centrocosto_id" class="form-control">
                                <option value="">Todos</option>
                                @foreach ($centrocosto_query as $cc)
                                    <option value="{{ $cc->id }}" @selected((int) ($filtros['centrocosto_id'] ?? 0) === (int) $cc->id)>
                                        {{ $cc->nombre }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="texto" class="col-lg-2 control-label text-right pr-2">Empleado</label>
                        <div class="col-lg-3">
                            <input type="text" name="texto" id="texto" class="form-control" autocomplete="off"
                                placeholder="Login, nombre o código" value="{{ $filtros['texto'] ?? '' }}">
                        </div>
                        <label for="estado" class="col-lg-2 control-label text-right pr-2">Estado</label>
                        <div class="col-lg-3">
                            <select name="estado" id="estado" class="form-control">
                                <option value="A" @selected($estadoActual === 'A')>Activos</option>
                                <option value="N" @selected($estadoActual === 'N')>Anulados</option>
                                <option value="TODOS" @selected($estadoActual === 'TODOS')>Todos</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label class="col-lg-2 control-label text-right pr-2">Orden</label>
                        <div class="col-lg-8">
                            <input type="hidden" name="orden_por" id="orden_por_input"
                                value="{{ $ordenPorCentrocosto ? 'centrocosto' : 'usuario' }}">
                            <button type="button"
                                class="btn btn-sm text-left {{ $ordenPorCentrocosto ? 'btn-success' : 'btn-outline-secondary' }} btn-toggle-orden-vianda"
                                id="btn_toggle_orden_vianda"
                                data-input="#orden_por_input"
                                title="Activo: agrupa por centro de costo. Desactivado: ordena por empleado.">
                                <i class="fa fa-check mr-1"></i>
                                Ordenar por centro de costo
                            </button>
                            <small class="text-muted d-inline-block ml-2">Off = por usuario</small>
                        </div>
                    </div>

                    <div class="form-group row mb-0">
                        <div class="col-lg-2"></div>
                        <div class="col-lg-10">
                            <input type="hidden" name="consultar" value="1">
                            <button type="submit" class="btn btn-primary btn-sm" id="btn-consultar">
                                <i class="fa fa-search"></i> Consultar
                            </button>
                        </div>
                    </div>
                </div>
            </form>

            <div class="card-body p-0 border-top">
                <div class="d-flex flex-wrap align-items-center justify-content-between px-3 py-2 border-bottom bg-light">
                    <div class="mb-1 mb-md-0">
                        @include('includes.exportar-tabla-queryparams', [
                            'ruta' => 'listar_reporte_vianda_gastronomia',
                            'queryparams' => $filtrosQuery ?? [],
                        ])
                    </div>
                    <div class="small mb-1 mb-md-0 text-md-right">
                        <span class="text-muted">Consumos:</span>
                        <strong>{{ (int) ($totales['consumos'] ?? 0) }}</strong>
                        · Ítems <strong>{{ (int) ($totales['items'] ?? 0) }}</strong>
                        · Costo <strong>{{ number_format((float) ($totales['costo'] ?? 0), 2, ',', '.') }}</strong>
                        · Venta <strong>{{ number_format((float) ($totales['venta'] ?? 0), 2, ',', '.') }}</strong>
                    </div>
                </div>

                @if (count($logosVista) > 0)
                    <div class="border-bottom px-3 py-2 d-flex flex-wrap align-items-center">
                        @foreach ($logosVista as $logo)
                            <img src="{{ $logo['uri'] }}" alt="{{ $logo['nombre'] }}" class="mr-2 mb-1" style="max-height: 48px; max-width: 140px;">
                        @endforeach
                    </div>
                @endif

                <style>
                    #tabla-paginada thead tr { background-color: #85C1E9; color: #17202A; }
                    #tabla-paginada thead th { font-weight: 600; border-color: #7fb3d5; }
                </style>
                <div class="table-responsive">
                    <table id="tabla-paginada" class="table table-striped table-bordered table-hover table-sm mb-0" style="font-size: 0.82rem;">
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Fecha</th>
                                <th>Hora</th>
                                <th>Login</th>
                                <th>Empleado</th>
                                <th>Centro de costo</th>
                                <th>Empresa</th>
                                <th class="text-right">Ítems</th>
                                <th class="text-right">Costo</th>
                                <th class="text-right">Venta</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($filas as $fila)
                                <tr @class(['text-muted' => $fila->estado === 'N'])>
                                    <td>{{ $fila->codigo_retiro }}</td>
                                    <td>{{ optional($fila->fecha)->format('d/m/Y') }}</td>
                                    <td>{{ $fila->hora }}</td>
                                    <td>{{ $fila->login_usuario }}</td>
                                    <td>{{ $fila->nombre_usuario }}</td>
                                    <td>{{ optional($fila->centrocosto)->nombre }}</td>
                                    <td>{{ optional($fila->empresa)->nombre }}</td>
                                    <td class="text-right">{{ (int) $fila->cantidad_items }}</td>
                                    <td class="text-right">{{ number_format((float) $fila->total_costo, 2, ',', '.') }}</td>
                                    <td class="text-right">{{ number_format((float) $fila->total_venta, 2, ',', '.') }}</td>
                                    <td>{{ $fila->etiquetaEstado() }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="11" class="text-center text-muted py-3">Sin consumos para el filtro seleccionado.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="card-footer clearfix d-flex flex-wrap align-items-center justify-content-between">
                    <span class="small text-muted mb-2 mb-md-0">
                        @if ($filas->total() > 0)
                            Mostrando {{ $filas->firstItem() }}–{{ $filas->lastItem() }} de {{ $filas->total() }} consumos
                        @else
                            Sin registros
                        @endif
                    </span>
                    {{ $filas->appends($filtrosQuery ?? [])->links() }}
                </div>
            </div>
        </div>

        @if (count($resumen_centrocosto) > 0)
            <div class="card card-secondary">
                <div class="card-header">
                    <h3 class="card-title">Resumen por centro de costo</h3>
                </div>
                <div class="card-body p-0">
                    <style>
                        #tabla-resumen-cc thead tr { background-color: #85C1E9; color: #17202A; }
                        #tabla-resumen-cc thead th { font-weight: 600; border-color: #7fb3d5; }
                    </style>
                    <div class="table-responsive">
                        <table id="tabla-resumen-cc" class="table table-striped table-bordered table-sm mb-0" style="font-size: 0.82rem;">
                            <thead>
                                <tr>
                                    <th>Centro de costo</th>
                                    <th class="text-right">Consumos</th>
                                    <th class="text-right">Ítems</th>
                                    <th class="text-right">Costo</th>
                                    <th class="text-right">Venta</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($resumen_centrocosto as $r)
                                    <tr>
                                        <td>{{ $r['centrocosto'] }}</td>
                                        <td class="text-right">{{ (int) $r['consumos'] }}</td>
                                        <td class="text-right">{{ (int) $r['items'] }}</td>
                                        <td class="text-right">{{ number_format((float) $r['costo'], 2, ',', '.') }}</td>
                                        <td class="text-right">{{ number_format((float) $r['venta'], 2, ',', '.') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr class="font-weight-bold bg-light">
                                    <td>Totales</td>
                                    <td class="text-right">{{ (int) ($totales['consumos'] ?? 0) }}</td>
                                    <td class="text-right">{{ (int) ($totales['items'] ?? 0) }}</td>
                                    <td class="text-right">{{ number_format((float) ($totales['costo'] ?? 0), 2, ',', '.') }}</td>
                                    <td class="text-right">{{ number_format((float) ($totales['venta'] ?? 0), 2, ',', '.') }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
