@extends("theme.$theme.layout")
@section('titulo')
    Tipos de transacciones de Caja
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/includes/listado-filtros.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/caja/tipotransaccion_caja/filtro.js")}}" type="text/javascript"></script>
@endsection

<?php use App\Support\Caja\TipotransaccionCajaListadoFiltros; ?>

@section('contenido')
@php
    $retornoListadoQuery = \App\Support\Listado\QueryRetornoListado::retornoLinksDesdeFiltrosQuery($filtrosQuery ?? []);
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Tipos de Transacciones de Caja</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-tipotransaccion-caja',
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => TipotransaccionCajaListadoFiltros::tieneCriteriosAplicados($filtros ?? []),
                        'limpiarUrl' => route('tipotransaccion_caja'),
                        'placeholder' => 'Búsqueda rápida (tolera errores de tipeo)…',
                        'toggleTarget' => '#panel-filtros-tipotransaccion-caja',
                        'toggleId' => 'btn-toggle-filtros-tipotransaccion-caja',
                        'inputId' => 'filtro_valor',
                        'nuevoRegistroUrl' => route('crear_tipotransaccion_caja', $retornoListadoQuery),
                        'nuevoRegistroCan' => 'crear-tipo-transaccion-caja',
                    ])
                </div>
            </div>
            <form method="get" action="{{ route('tipotransaccion_caja') }}" id="form-filtros-tipotransaccion-caja" class="mb-0">
                @include('caja.tipotransaccion_caja.partials.filtros_listado', [
                    'limpiarUrl' => route('tipotransaccion_caja'),
                ])
            </form>
            <div class="card-body table-responsive p-0">
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'lista_tipotransaccion_caja',
                    'queryparams' => $filtrosQuery ?? [],
                ])
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th class="width20">ID</th>
                            <th>Nombre</th>
                            <th>Operaci&oacute;n</th>
                            <th>Abreviatura</th>
                            <th>Signo</th>
                            <th>Estado</th>
                            <th class="width80" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($datas as $data)
                        <tr>
                            <td>{{ $data->id }}</td>
                            <td>{{ $data->nombre }}</td>
                            <td>{{ $operacionEnum[$data->operacion] ?? $data->operacion }}</td>
                            <td>{{ $data->abreviatura }}</td>
                            <td>{{ $signoEnum[$data->signo] ?? $data->signo }}</td>
                            <td>{{ $estadoEnum[$data->estado] ?? $data->estado }}</td>
                            <td>
                                @if (can('editar-tipo-transaccion-caja', false))
                                    <a href="{{ route('editar_tipotransaccion_caja', ['id' => $data->id] + $retornoListadoQuery) }}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                        <i class="fa fa-edit"></i>
                                    </a>
                                @endif
                                @if (can('borrar-tipo-transaccion-caja', false))
                                <form action="{{ route('eliminar_tipotransaccion_caja', ['id' => $data->id]) }}" class="d-inline form-eliminar" method="POST">
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
@endsection
