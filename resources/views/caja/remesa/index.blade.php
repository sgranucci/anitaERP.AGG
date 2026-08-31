@extends("theme.$theme.layout")
@section('titulo')
    Remesas
@endsection

@section("scripts")
<style>
    #tabla-paginada thead th { background: #85C1E9; color: #17202A; }
</style>
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/includes/listado-filtros.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/caja/remesa/filtro.js') }}" type="text/javascript"></script>
@endsection

@php
    use App\Support\Caja\Remesa\RemesaSupport;
    use App\Support\Caja\RemesaListadoFiltros;

    $tipoLabels = collect($tipo_enum ?? Remesa::$enumTipo)->pluck('nombre', 'valor')->all();
    $estadoLabels = collect($estado_enum ?? Remesa::$enumEstado)->pluck('nombre', 'valor')->all();
@endphp

@section('contenido')
@php
    $retornoListadoQuery = \App\Support\Listado\QueryRetornoListado::retornoLinksDesdeFiltrosQuery($filtrosQuery ?? []);
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Remesas</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-remesa',
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => RemesaListadoFiltros::tieneCriteriosTexto($filtros ?? []),
                        'limpiarUrl' => route('remesa', RemesaListadoFiltros::paraQueryStringEmpresa($filtros ?? [])),
                        'placeholder' => 'B&uacute;squeda r&aacute;pida&hellip;',
                        'toggleTarget' => '#panel-filtros-remesa',
                        'toggleId' => 'btn-toggle-filtros-remesa',
                        'inputId' => 'filtro_valor',
                        'nuevoRegistroUrl' => route('crear_remesa', $retornoListadoQuery),
                        'nuevoRegistroCan' => 'crear-remesa',
                    ])
                    @if (can('listar-remesa-reporte', false))
                        <a href="{{ route('remesa_reporte') }}"
                           class="btn btn-outline-secondary btn-sm ml-1"
                           title="Reporte por cuenta de caja">
                            <i class="fa fa-file-alt"></i> Reporte
                        </a>
                    @endif
                    @if (can('configurar-remesa', false))
                        <a href="{{ route('configurar_remesa', $retornoListadoQuery) }}"
                           class="btn btn-outline-secondary btn-sm ml-1"
                           title="Configuraci&oacute;n de cuentas">
                            <i class="fa fa-cog"></i> Configurar
                        </a>
                    @endif
                </div>
            </div>
            <form method="get" action="{{ route('remesa') }}" id="form-filtros-remesa" class="mb-0">
                @include('caja.remesa.partials.filtros_listado', [
                    'limpiarUrl' => route('remesa', RemesaListadoFiltros::paraQueryStringEmpresa($filtros ?? [])),
                ])
            </form>
            @include('caja.remesa.partials.filtros_externos')
            <div class="card-body table-responsive p-0">
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'lista_remesa',
                    'queryparams' => $filtrosQuery ?? [],
                ])
                <table class="table table-striped table-bordered table-hover mb-0" id="tabla-paginada">
                    <thead>
                        <tr>
                            <th class="width20">ID</th>
                            <th>N&deg;</th>
                            <th>Fecha</th>
                            <th>Tipo</th>
                            <th>Empresa</th>
                            <th class="text-right">Destino</th>
                            <th class="text-right">Origen</th>
                            <th>Remito</th>
                            <th class="width120">Estado</th>
                            <th class="width100">V&iacute;nculos</th>
                            <th class="width80" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($datas as $data)
                        <tr class="{{ in_array($data->estado, [RemesaSupport::ESTADO_ANULADA, RemesaSupport::ESTADO_REVERTIDA], true) ? 'text-muted' : '' }}">
                            <td>{{ $data->id }}</td>
                            <td>{{ $data->numero }}</td>
                            <td>{{ $data->fecha?->format('d/m/Y') }}</td>
                            <td>{{ $tipoLabels[$data->tipo] ?? $data->tipo }}</td>
                            <td>{{ $data->empresa->nombre ?? '' }}</td>
                            <td class="text-right">{{ number_format((float) $data->importe_destino, 2, ',', '.') }}</td>
                            <td class="text-right">{{ number_format((float) $data->importe_origen, 2, ',', '.') }}</td>
                            <td>{{ $data->remito }}</td>
                            <td>{{ $estadoLabels[$data->estado] ?? $data->estado }}</td>
                            <td class="text-nowrap small">
                                @if ((int) ($data->asiento_id ?? 0) > 0 && (can('listar-asiento', false) || can('editar-asiento', false)))
                                    <a href="{{ route('editar_asiento', ['id' => $data->asiento_id, 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}"
                                       class="text-primary" target="_blank" rel="noopener" title="Asiento">
                                        Asi {{ $data->asiento_id }}
                                    </a>
                                @endif
                                @if ((int) ($data->caja_movimiento_id ?? 0) > 0 && (can('listar-ingresos-egresos-caja', false) || can('editar-ingresos-egresos-caja', false)))
                                    @if ((int) ($data->asiento_id ?? 0) > 0)<br>@endif
                                    <a href="{{ route('editar_ingresoegreso', ['id' => $data->caja_movimiento_id, 'origen' => 'modal_consulta']) }}"
                                       class="text-primary" target="_blank" rel="noopener" title="Movimiento de caja">
                                        Caja {{ $data->caja_movimiento_id }}
                                    </a>
                                @endif
                            </td>
                            <td class="text-nowrap">
                                @if (can('editar-remesa', false) && $data->estado === RemesaSupport::ESTADO_CONFIRMADA)
                                    <a href="{{ route('editar_remesa', ['id' => $data->id] + $retornoListadoQuery) }}"
                                       class="btn-accion-tabla tooltipsC" title="Editar">
                                        <i class="fa fa-edit"></i>
                                    </a>
                                @endif
                                @if (can('revertir-remesa', false) && $data->estado === RemesaSupport::ESTADO_CONFIRMADA)
                                <form action="{{ route('revertir_remesa', ['id' => $data->id]) }}" class="d-inline form-revertir" method="POST"
                                      onsubmit="return confirm('&iquest;Revertir esta remesa? Se grabar&aacute;n asiento y movimiento de caja compensatorios con fecha de hoy (el original se conserva).');">
                                    @csrf
                                    <button type="submit" class="btn-accion-tabla tooltipsC" title="Revertir con fecha de hoy (tesorer&iacute;a)">
                                        <i class="fa fa-undo text-warning"></i>
                                    </button>
                                </form>
                                @endif
                                @if (can('anular-remesa', false) && $data->estado === RemesaSupport::ESTADO_CONFIRMADA)
                                <form action="{{ route('anular_remesa', ['id' => $data->id]) }}" class="d-inline form-anular" method="POST"
                                      onsubmit="return confirm('&iquest;ELIMINAR f&iacute;sicamente esta remesa, su asiento/ctamov y movimientos de caja? Solo con per&iacute;odo abierto (administrador).');">
                                    @csrf @method('delete')
                                    <button type="submit" class="btn-accion-tabla tooltipsC" title="Anular / borrar f&iacute;sico (administrador)">
                                        <i class="fa fa-times-circle text-danger"></i>
                                    </button>
                                </form>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="11" class="text-center text-muted py-4">Sin remesas para los filtros aplicados.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if (method_exists($datas, 'links'))
                <div class="card-footer clearfix">
                    {{ $datas->appends($filtrosQuery ?? [])->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
