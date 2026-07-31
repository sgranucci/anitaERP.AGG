@extends("theme.$theme.layout")
@section('titulo')
    Solicitudes de pago
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/includes/listado-filtros.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/solicitudpago/solicitudpago/filtro.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/solicitudpago/solicitudpago/familia_vinculos.js")}}" type="text/javascript"></script>
@if (can('crear-solicitud-pago', false))
<script src="{{asset("assets/pages/scripts/solicitudpago/solicitudpago/carga_masiva.js")}}" type="text/javascript"></script>
@endif
@endsection

<?php use App\Support\Solicitudpago\SolicitudpagoListadoFiltros; ?>

@section('contenido')
@php
    $retornoListadoQuery = \App\Support\Listado\QueryRetornoListado::retornoLinksDesdeFiltrosQuery($filtrosQuery ?? []);
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Solicitudes de pago</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    @if (can('crear-solicitud-pago', false))
                        <button type="button" class="btn btn-success btn-sm mr-1" id="btn-carga-masiva-sp"
                                title="Importar solicitudes desde CSV Anita">
                            <i class="fa fa-upload"></i> Carga masiva
                        </button>
                    @endif
                    @if (!empty($puedeVerTodas) && !empty($alcanceToggleUrl))
                        @if (($alcanceListado ?? 'todas') === 'mi_cc')
                            <a href="{{ $alcanceToggleUrl }}"
                               class="btn btn-warning btn-sm mr-1"
                               title="Mostrar todas las solicitudes sin filtrar por centro de costo">
                                <i class="fa fa-globe"></i> Ver todas
                            </a>
                        @else
                            <a href="{{ $alcanceToggleUrl }}"
                               class="btn btn-outline-light btn-sm mr-1"
                               title="Filtrar solo las de su centro de costo">
                                <i class="fa fa-building"></i> Solo mi centro de costo
                            </a>
                        @endif
                    @endif
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-solicitudpago',
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => SolicitudpagoListadoFiltros::tieneCriteriosAplicados($filtros ?? []),
                        'limpiarUrl' => $limpiarFiltrosUrl ?? route('consultar_solicitudpago', ['limpiar_filtros' => 1]),
                        'placeholder' => 'Búsqueda rápida (código, detalle, beneficiario)…',
                        'toggleTarget' => '#panel-filtros-solicitudpago',
                        'toggleId' => 'btn-toggle-filtros-solicitudpago',
                        'inputId' => 'filtro_valor',
                        'nuevoRegistroUrl' => route('crear_solicitudpago', $retornoListadoQuery),
                        'nuevoRegistroCan' => 'crear-solicitud-pago',
                    ])
                </div>
            </div>
            <form method="get" action="{{ route('consultar_solicitudpago') }}" id="form-filtros-solicitudpago" class="mb-0">
                @if (($alcanceListado ?? '') === 'mi_cc')
                    <input type="hidden" name="alcance" value="mi_cc">
                @endif
                @include('solicitudpago.solicitudpago.partials.filtros_listado', [
                    'limpiarUrl' => $limpiarFiltrosUrl ?? route('consultar_solicitudpago', ['limpiar_filtros' => 1]),
                ])
            </form>
            @include('solicitudpago.solicitudpago.partials.filtros_externos')
            <div class="card-body table-responsive p-0">
                @if (!empty($puedeVerTodas) && ($alcanceListado ?? '') === 'mi_cc')
                    <div class="alert alert-warning py-2 mb-0 mx-3 mt-3 small">
                        <i class="fa fa-building"></i>
                        Alcance: <strong>solo su centro de costo</strong>
                        (empresas asignadas + CC del usuario). Use <strong>Ver todas</strong> para listar sin esa restricción.
                    </div>
                @elseif (!empty($puedeVerTodas))
                    <div class="alert alert-secondary py-2 mb-0 mx-3 mt-3 small">
                        <i class="fa fa-globe"></i>
                        Alcance: <strong>todas las solicitudes</strong>
                        (permiso listar-todas). Use <strong>Solo mi centro de costo</strong> para acotar.
                    </div>
                @endif
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'lista_solicitudpago',
                    'queryparams' => $filtrosQuery ?? [],
                ])
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead>
                        <tr>
                            <th>C&oacute;digo</th>
                            <th>Empresa</th>
                            <th>Fecha</th>
                            <th>Concepto</th>
                            <th>Proveedor / Beneficiario</th>
                            <th>Detalle</th>
                            <th class="text-right">Monto</th>
                            <th>Tratamiento</th>
                            <th>Estado</th>
                            <th>SP madre</th>
                            <th>Cuotas</th>
                            <th class="width120 text-nowrap" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($coleccion as $data)
                        @php
                            $esSuspendidaFila = ($data->estado ?? '') === \App\Support\Solicitudpago\SolicitudpagoEstados::SUSPENDIDA;
                            $esHija = (int) ($data->solicitudpago_madre_id ?? 0) > 0;
                            $esMadrePlan = ((int) ($data->cuotas_total_count ?? 0) > 0) || ((int) ($data->hijas_count ?? 0) > 0);
                            $cuotasPend = (int) ($data->cuotas_pendientes_count ?? 0);
                            $cuotasTotal = (int) ($data->cuotas_total_count ?? 0);
                        @endphp
                        <tr @if($esSuspendidaFila) class="table-secondary" @endif>
                            <td>
                                @if (can('editar-solicitud-pago', false))
                                    <a href="{{ route('editar_solicitudpago', ['id' => $data->id] + $retornoListadoQuery) }}"
                                       class="text-primary font-weight-bold">
                                        {{ $data->codigo }}
                                    </a>
                                @else
                                    {{ $data->codigo }}
                                @endif
                                @if ($esMadrePlan)
                                    <span class="badge badge-primary ml-1" title="SP madre con plan/cuotas">Madre</span>
                                @elseif ($esHija)
                                    <span class="badge badge-light border ml-1" title="SP hija de un plan">Hija</span>
                                @endif
                            </td>
                            <td>{{ optional($data->empresas)->nombre ?? '—' }}</td>
                            <td>{{ optional($data->fecha)->format('d/m/Y') }}</td>
                            <td>{{ optional($data->conceptos)->nombre ?? '—' }}</td>
                            <td>
                                @if ($data->proveedores)
                                    {{ $data->proveedores->nombre }}
                                @elseif ($data->beneficiario)
                                    {{ $data->beneficiario }}
                                @else
                                    —
                                @endif
                            </td>
                            <td>{{ $data->detalle ?: '—' }}</td>
                            <td class="text-right">{{ number_format((float) $data->monto, 2, ',', '.') }}</td>
                            <td>
                                @foreach ($tratamiento_enum as $opt)
                                    @if ($opt['valor'] === $data->tratamiento) {{ $opt['nombre'] }} @endif
                                @endforeach
                            </td>
                            <td>
                                @include('solicitudpago.solicitudpago.partials.estado_badge', ['estado' => $data->estado ?? ''])
                            </td>
                            <td>
                                @if ($data->madre)
                                    <a href="{{ route('editar_solicitudpago', ['id' => $data->madre->id] + $retornoListadoQuery) }}"
                                       class="text-primary" title="Abrir SP madre">
                                        #{{ $data->madre->codigo }}
                                    </a>
                                @else
                                    —
                                @endif
                            </td>
                            <td>
                                @if ($cuotasTotal > 0)
                                    <a href="{{ route('editar_solicitudpago', ['id' => $data->id] + $retornoListadoQuery) }}#tab-cuotas"
                                       class="text-primary" title="Ver cuotas del plan">
                                        {{ $cuotasTotal - $cuotasPend }}/{{ $cuotasTotal }}
                                        <span class="text-muted small">({{ $cuotasPend }} pend.)</span>
                                    </a>
                                @else
                                    {{ $cuotasPend }}
                                @endif
                            </td>
                            <td class="text-nowrap">
                                @if (can('listar-solicitud-pago', false) || can('editar-solicitud-pago', false))
                                    <a href="{{ route('imprimir_pdf_solicitudpago', $data->id) }}"
                                       class="btn-accion-tabla tooltipsC"
                                       title="Emitir comprobante PDF"
                                       target="_blank" rel="noopener noreferrer">
                                        <i class="fas fa-print"></i>
                                    </a>
                                @endif
                                @if ($cuotasTotal > 0 || $esHija)
                                    <button type="button"
                                            class="btn-accion-tabla tooltipsC btn-sp-ver-plan"
                                            title="Ver plan / cuotas vinculadas"
                                            data-url="{{ route('familia_vinculos_solicitudpago', $data->id) }}"
                                            data-codigo="{{ $data->codigo }}">
                                        <i class="fa fa-sitemap text-info"></i>
                                    </button>
                                @endif
                                @if (can('editar-solicitud-pago', false))
                                    <a href="{{ route('editar_solicitudpago', ['id' => $data->id] + $retornoListadoQuery) }}" class="btn-accion-tabla tooltipsC" title="Editar">
                                        <i class="fa fa-edit"></i>
                                    </a>
                                @endif
                                @if (can('borrar-solicitud-pago', false))
                                    <form action="{{ route('eliminar_solicitudpago', ['id' => $data->id]) }}" class="d-inline form-eliminar" method="POST">
                                        @csrf @method("delete")
                                        <button type="submit" class="btn-accion-tabla eliminar tooltipsC" title="Eliminar">
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
{{ $coleccion->appends($filtrosQuery ?? [])->links() }}
@include('includes.solicitudpago.modal_familia_vinculos')
@if (can('crear-solicitud-pago', false))
    @include('solicitudpago.solicitudpago.partials.modal_carga_masiva')
@endif
@endsection
