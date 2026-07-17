@extends("theme.$theme.layout")
@section('titulo')
    Solicitudes de pago
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/includes/listado-filtros.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/solicitudpago/solicitudpago/filtro.js")}}" type="text/javascript"></script>
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
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-solicitudpago',
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => SolicitudpagoListadoFiltros::tieneCriteriosAplicados($filtros ?? []),
                        'limpiarUrl' => route('consultar_solicitudpago'),
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
                @include('solicitudpago.solicitudpago.partials.filtros_listado', [
                    'limpiarUrl' => route('consultar_solicitudpago'),
                ])
            </form>
            <div class="card-body table-responsive p-0">
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'lista_solicitudpago',
                    'queryparams' => $filtrosQuery ?? [],
                ])
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead>
                        <tr>
                            <th>C&oacute;digo</th>
                            <th>Fecha</th>
                            <th>Concepto</th>
                            <th>Proveedor / Beneficiario</th>
                            <th class="text-right">Monto</th>
                            <th>Tratamiento</th>
                            <th>Estado</th>
                            <th>SP madre</th>
                            <th>Cuotas pend.</th>
                            <th class="width80" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($coleccion as $data)
                        <tr>
                            <td>{{ $data->codigo }}</td>
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
                            <td class="text-right">{{ number_format((float) $data->monto, 2, ',', '.') }}</td>
                            <td>
                                @foreach ($tratamiento_enum as $opt)
                                    @if ($opt['valor'] === $data->tratamiento) {{ $opt['nombre'] }} @endif
                                @endforeach
                            </td>
                            <td>
                                @foreach ($estado_enum as $opt)
                                    @if ($opt['valor'] === $data->estado) {{ $opt['nombre'] }} @endif
                                @endforeach
                            </td>
                            <td>{{ optional($data->madre)->codigo ?? '—' }}</td>
                            <td>{{ $data->cuotas_pendientes_count ?? 0 }}</td>
                            <td>
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
@endsection
