@extends("theme.$theme.layout")
@section('titulo')
    Viandas del día
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
<script>
window.VIANDA_DIA = {
    csrf: @json(csrf_token()),
};
</script>
<script src="{{ asset('assets/pages/scripts/ventas/vianda/dia.js') }}" type="text/javascript"></script>
@endsection

@section('contenido')
@php
    $estadoActual = $filtros['estado'] ?? 'A';
    $textoFiltro = trim((string) ($filtros['texto'] ?? ''));
    $empresaIdFiltro = (int) ($filtros['empresa_id'] ?? 0);
    $centrocostoIdFiltro = (int) ($filtros['centrocosto_id'] ?? 0);
    $tieneFiltrosExtra = $textoFiltro !== ''
        || $centrocostoIdFiltro > 0
        || $estadoActual !== 'A'
        || ($empresaIdFiltro > 0 && ($empresa_query ?? collect())->count() > 1);
    $reporteQuery = array_filter([
        'consultar' => 1,
        'fecha_desde' => $fecha,
        'fecha_hasta' => $fecha,
        'empresa_id' => $empresaIdFiltro ?: null,
        'centrocosto_id' => $centrocostoIdFiltro ?: null,
        'texto' => $textoFiltro !== '' ? $textoFiltro : null,
        'estado' => $estadoActual !== 'A' ? $estadoActual : null,
    ], fn ($v) => $v !== null && $v !== '');
@endphp
@include('ventas.vianda.dia.partials.estilos_acciones_tabla')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="alert alert-info py-2 mb-2">
            Consulta operativa de viandas · Fecha <strong>{{ \Illuminate\Support\Carbon::parse($fecha)->format('d/m/Y') }}</strong>
            @if ($estadoActual === 'A')
                · solo <strong>activas</strong>
            @elseif ($estadoActual === 'N')
                · solo <strong>borradas</strong>
            @else
                · <strong>todas</strong> (activas y borradas)
            @endif
            @if (! request()->filled('fecha'))
                <span class="text-muted">(hoy por defecto)</span>
            @endif
        </div>
        <div class="card card-info">
            <div class="card-header vianda-dia-card-header">
                <h3 class="card-title mb-0">Viandas del día</h3>
                <div class="vianda-dia-header-acciones">
                    @if (can('listar-reporte-vianda-gastronomia', false))
                        <a href="{{ route('consultar_reporte_vianda_gastronomia', $reporteQuery) }}"
                            class="btn btn-sm btn-vianda-header" title="Reporte por período y exportación">
                            <i class="fa fa-chart-bar"></i> Reporte
                        </a>
                    @endif
                </div>
            </div>
            <div class="card-body py-2 border-bottom bg-light">
                <form action="{{ route('viandas_dia_gastronomia') }}" method="GET" id="form-viandas-dia"
                    class="d-flex flex-wrap align-items-end vianda-dia-filtros-form mb-0">
                    @if (($empresa_query ?? collect())->count() > 1)
                        <div class="form-group mb-2 mb-md-0 mr-2">
                            @include('includes.listado.filtro_empresa_asignada_inline', [
                                'empresa_query' => $empresa_query,
                                'empresa_id' => $empresaIdFiltro,
                                'id' => 'empresa_id_vianda_dia',
                                'permite_todas' => true,
                                'opcion_todas' => 'Todas',
                                'select_class' => 'form-control form-control-sm',
                                'label_class' => 'small mb-0 d-block',
                                'label' => 'Empresa',
                            ])
                        </div>
                    @elseif (($empresa_query ?? collect())->count() === 1)
                        <input type="hidden" name="empresa_id" value="{{ $empresa_query->first()->id }}">
                    @endif
                    <div class="form-group mb-2 mb-md-0 mr-2">
                        <label for="fecha_vianda_dia" class="small mb-0 d-block">Fecha</label>
                        <input type="date" id="fecha_vianda_dia" name="fecha" value="{{ $fecha }}"
                            class="form-control form-control-sm">
                    </div>
                    <div class="form-group mb-2 mb-md-0 mr-2">
                        <label for="centrocosto_id_vianda_dia" class="small mb-0 d-block">Centro de costo</label>
                        <select name="centrocosto_id" id="centrocosto_id_vianda_dia"
                            class="form-control form-control-sm" style="min-width:160px;">
                            <option value="">Todos</option>
                            @foreach ($centrocosto_query as $cc)
                                <option value="{{ $cc->id }}" @selected($centrocostoIdFiltro === (int) $cc->id)>
                                    {{ $cc->nombre }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group mb-2 mb-md-0 mr-2">
                        <label for="estado_vianda_dia" class="small mb-0 d-block">Estado</label>
                        <select name="estado" id="estado_vianda_dia" class="form-control form-control-sm" style="min-width:110px;">
                            <option value="A" @selected($estadoActual === 'A')>Activas</option>
                            <option value="N" @selected($estadoActual === 'N')>Borradas</option>
                            <option value="TODOS" @selected($estadoActual === 'TODOS')>Todas</option>
                        </select>
                    </div>
                    <div class="form-group mb-2 mb-md-0 mr-2">
                        <label for="texto_vianda_dia" class="small mb-0 d-block">Empleado</label>
                        <div class="btn-group">
                            <input type="text" name="texto" id="texto_vianda_dia" class="form-control form-control-sm"
                                placeholder="Login, nombre o código" value="{{ $textoFiltro }}"
                                autocomplete="off" style="min-width:160px;">
                            <button type="submit" class="btn btn-default btn-sm" title="Buscar">
                                <span class="fa fa-search"></span>
                            </button>
                        </div>
                    </div>
                    @if ($tieneFiltrosExtra)
                        <div class="form-group mb-2 mb-md-0">
                            <label class="small mb-0 d-block">&nbsp;</label>
                            <a href="{{ route('viandas_dia_gastronomia', array_filter([
                                'fecha' => $fecha,
                                'empresa_id' => $empresaIdFiltro ?: null,
                            ])) }}"
                                class="btn btn-outline-secondary btn-sm" title="Quitar filtros de texto y estado">
                                Limpiar
                            </a>
                        </div>
                    @endif
                </form>
            </div>
            <div class="card-body p-0">
                <div class="d-flex flex-wrap align-items-center justify-content-end px-3 py-2 border-bottom bg-light">
                    <div class="small mb-1 mb-md-0 text-md-right" title="Totales de todas las viandas que coinciden con los filtros">
                        <span class="text-muted">Totales filtro:</span>
                        <strong>{{ (int) ($totales['consumos'] ?? 0) }}</strong> vianda(s)
                        · Ítems <strong>{{ (int) ($totales['items'] ?? 0) }}</strong>
                        · Costo <strong>{{ number_format((float) ($totales['costo'] ?? 0), 2, ',', '.') }}</strong>
                        · Venta <strong>{{ number_format((float) ($totales['venta'] ?? 0), 2, ',', '.') }}</strong>
                        @if (($totales['anulados'] ?? 0) > 0)
                            · <span class="text-danger">Borradas {{ (int) $totales['anulados'] }}</span>
                        @endif
                    </div>
                </div>
                <div class="table-responsive">
                    <table id="tabla-viandas-dia" class="table table-striped table-bordered table-hover table-sm mb-0" style="font-size: 0.82rem;">
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
                                <th class="width40" data-orderable="false"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($filas as $fila)
                                @php $borrada = $fila->estado === 'N'; @endphp
                                <tr @class(['text-muted' => $borrada])>
                                    <td>{{ $fila->codigo_retiro }}</td>
                                    <td class="text-nowrap">{{ optional($fila->fecha)->format('d/m/Y') }}</td>
                                    <td>{{ $fila->hora }}</td>
                                    <td>{{ $fila->login_usuario }}</td>
                                    <td>{{ $fila->nombre_usuario }}</td>
                                    <td>{{ optional($fila->centrocosto)->nombre }}</td>
                                    <td>{{ optional($fila->empresa)->nombre }}</td>
                                    <td class="text-right">{{ (int) $fila->cantidad_items }}</td>
                                    <td class="text-right">{{ number_format((float) $fila->total_costo, 2, ',', '.') }}</td>
                                    <td class="text-right">{{ number_format((float) $fila->total_venta, 2, ',', '.') }}</td>
                                    <td>
                                        @if ($borrada)
                                            <span class="badge badge-danger">Borrada</span>
                                        @else
                                            <span class="badge badge-success">Activa</span>
                                        @endif
                                    </td>
                                    <td class="viandas-dia-tabla-acciones text-nowrap">
                                        <a href="{{ route('viandas_dia_ver', ['consumoId' => $fila->id] + ($filtrosQuery ?? [])) }}"
                                            class="btn-accion-tabla tooltipsC" title="Ver detalle de la vianda">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        @if (! $borrada)
                                            <button type="button" class="btn-accion-tabla tooltipsC js-vianda-reimprimir"
                                                data-url="{{ route('viandas_dia_reimprimir', $fila->id) }}"
                                                data-codigo="{{ $fila->codigo_retiro }}"
                                                title="Reimprimir voucher">
                                                <i class="fa fa-print text-secondary"></i>
                                            </button>
                                            @if ($puede_borrar)
                                                <button type="button" class="btn-accion-tabla tooltipsC js-vianda-borrar"
                                                    data-url="{{ route('viandas_dia_borrar', $fila->id) }}"
                                                    data-codigo="{{ $fila->codigo_retiro }}"
                                                    title="Borrar vianda (devuelve stock)">
                                                    <i class="fa fa-trash text-danger"></i>
                                                </button>
                                            @endif
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="12" class="text-center text-muted py-4">
                                        Sin viandas para la fecha y filtros indicados.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                    @if ($filas->hasPages())
                        <div class="d-flex flex-wrap justify-content-between align-items-center px-3 py-2 border-top bg-light">
                            <small class="text-muted mb-2 mb-md-0">
                                Mostrando {{ $filas->firstItem() }}–{{ $filas->lastItem() }} de {{ $filas->total() }} vianda(s)
                            </small>
                            <div>{{ $filas->onEachSide(1)->links() }}</div>
                        </div>
                    @elseif ($filas->total() > 0)
                        <div class="px-3 py-2 border-top bg-light">
                            <small class="text-muted">{{ $filas->total() }} vianda(s) en la página.</small>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Modal borrar vianda --}}
<div class="modal fade" id="modal-vianda-borrar" tabindex="-1" role="dialog" data-backdrop="static">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white py-2">
                <h5 class="modal-title mb-0"><i class="fa fa-trash mr-1"></i> Borrar vianda</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <p class="mb-2">
                    Va a borrar la vianda <strong id="vianda-borrar-codigo">—</strong>.
                    Se marcará como <strong>borrada</strong> y se devolverá el stock descargado (plato e insumos).
                </p>
                <div class="form-group mb-0">
                    <label class="mb-1" for="vianda-borrar-motivo">Motivo (opcional)</label>
                    <textarea class="form-control form-control-sm" id="vianda-borrar-motivo" rows="2" maxlength="255"
                        placeholder="Ej. carga errónea, empleado duplicado…"></textarea>
                </div>
                <div class="alert alert-danger d-none mt-2 mb-0 py-2" id="vianda-borrar-error"></div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" id="vianda-borrar-confirmar">
                    <i class="fa fa-trash"></i> Borrar vianda
                </button>
            </div>
        </div>
    </div>
</div>
@endsection
