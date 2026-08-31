@extends("theme.$theme.layout")
@section('titulo')
    Ingresos y Egresos de Caja
@endsection

@section("scripts")
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/includes/listado-filtros.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/caja/ingresoegreso/filtro.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/caja/ingresoegreso/anular_revertir.js') }}" type="text/javascript"></script>
@endsection

@php
    use App\Support\Caja\IngresoEgresoListadoFiltros;
    $retornoListadoQuery = \App\Support\Listado\QueryRetornoListado::retornoLinksDesdeFiltrosQuery($filtrosQuery ?? []);
    $limpiarUrl = route('ingresoegreso', IngresoEgresoListadoFiltros::paraQueryStringEmpresa($filtros ?? []));
@endphp

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Ingresos y Egresos de Caja</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-ingresoegreso',
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => IngresoEgresoListadoFiltros::tieneCriteriosAplicados($filtros ?? []),
                        'limpiarUrl' => $limpiarUrl,
                        'placeholder' => 'Búsqueda rápida (tolera errores de tipeo)…',
                        'toggleTarget' => '#panel-filtros-ingresoegreso',
                        'toggleId' => 'btn-toggle-filtros-ingresoegreso',
                        'inputId' => 'filtro_valor',
                        'nuevoRegistroUrl' => route('crear_ingresoegreso', $retornoListadoQuery),
                        'nuevoRegistroCan' => 'crear-ingresos-egresos-caja',
                    ])
                </div>
            </div>
            <form method="get" action="{{ route('ingresoegreso') }}" id="form-filtros-ingresoegreso" class="mb-0">
                @include('caja.ingresoegreso.partials.filtros_listado', [
                    'limpiarUrl' => $limpiarUrl,
                ])
            </form>
            @include('caja.ingresoegreso.partials.filtros_externos')
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
            <div class="card-body table-responsive p-0">
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'lista_ingresoegreso',
                    'queryparams' => $filtrosQuery ?? [],
                ])
                <table class="table table-striped table-bordered table-hover mb-0" id="tabla-paginada">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th class="width20">ID</th>
                            <th>Empresa</th>
                            <th>Número</th>
                            <th>Fecha</th>
                            <th>Tipo de transacción</th>
                            <th>Concepto</th>
                            <th>Detalle</th>
                            @if (config('app.empresa') == 'Iguassu Travel')
                                <th>Orden de servicio</th>
                            @endif
                            <th class="text-right">Monto en $</th>
                            <th>Movimientos</th>
                            <th class="width80" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($caja_movimiento as $data)
                        <tr>
                            <td>{{ $data->id }}</td>
                            <td>{{ $data->nombreempresa }}</td>
                            <td>{{ $data->numerotransaccion }}</td>
                            <td>{{ $data->fecha ? date('d/m/Y', strtotime($data->fecha)) : '' }}</td>
                            <td>
                                @if (!empty($data->abreviaturatipotransaccion_caja))
                                    <span class="text-muted">{{ $data->abreviaturatipotransaccion_caja }}</span>
                                    —
                                @endif
                                {{ $data->nombretipotransaccion_caja }}
                            </td>
                            <td>{{ $data->nombreconceptogasto ?? '' }}</td>
                            <td>{{ $data->detalle ?? '' }}</td>
                            @if (config('app.empresa') == 'Iguassu Travel')
                                <td>{{ $data->ordenservicio_id }}</td>
                            @endif
                            <td class="text-right">
                                @php $totalIngreso = 0; $totalEgreso = 0; @endphp
                                @foreach ($data->caja_movimiento_cuentacajas as $movimiento)
                                    @php
                                        $coef = ($movimiento->moneda_id > 1) ? $movimiento->cotizacion : 1.;
                                        $totalIngreso += ($movimiento->monto > 0 ? $movimiento->monto * $coef : 0);
                                        $totalEgreso += ($movimiento->monto < 0 ? abs($movimiento->monto * $coef) : 0);
                                    @endphp
                                @endforeach
                                {{ number_format($totalIngreso != 0 ? $totalIngreso : $totalEgreso, 2, ',', '.') }}
                            </td>
                            <td>
                                <ul class="mb-0 pl-3 small">
                                @foreach ($data->caja_movimiento_cuentacajas as $movimiento)
                                    <li>
                                        {{ $movimiento->cuentacajas->nombre ?? '' }}
                                        {{ number_format((float) $movimiento->monto, 2, ',', '.') }}
                                    </li>
                                @endforeach
                                </ul>
                            </td>
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
                                    <a href="{{ route('editar_ingresoegreso', ['id' => $data->id, 'origen' => 'ingresoegreso'] + $retornoListadoQuery) }}"
                                       class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                        <i class="fa fa-edit"></i>
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
                            <td colspan="{{ config('app.empresa') == 'Iguassu Travel' ? 11 : 10 }}" class="text-center text-muted py-4">
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
@endsection
