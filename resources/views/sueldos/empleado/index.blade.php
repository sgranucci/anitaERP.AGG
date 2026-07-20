@extends("theme.$theme.layout")
@section('titulo')
    Empleados
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/includes/listado-filtros.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/sueldos/empleado/filtro.js")}}" type="text/javascript"></script>
@endsection

<?php use App\Support\Sueldos\EmpleadoSueldosListadoFiltros; use App\Support\Sueldos\EmpleadoEstados; ?>

@section('contenido')
@php
    $retornoListadoQuery = \App\Support\Listado\QueryRetornoListado::retornoLinksDesdeFiltrosQuery($filtrosQuery ?? []);
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Empleados</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    @if (can('actualizar-empleado-sueldos', false))
                        <form action="{{ route('sincronizar_empleado_sueldos_anita') }}" method="POST" class="d-inline mr-1"
                              onsubmit="return confirm('¿Sincronizar empleados desde Anita? Solo se agregarán los legajos que falten, con su historia (emping), leyendas (empley) y bases.');">
                            @csrf
                            <button type="submit" class="btn btn-outline-primary btn-sm">
                                <i class="fa fa-fw fa-refresh"></i> Sincronizar desde Anita
                            </button>
                        </form>
                        <form action="{{ route('vincular_empleado_sueldos_domicilios') }}" method="POST" class="d-inline mr-1"
                              onsubmit="return confirm('¿Vincular provincias y localidades con los maestros? Solo completa los empleados que hoy tienen el texto sin vincular; no pisa datos ya vinculados.');">
                            @csrf
                            <button type="submit" class="btn btn-outline-secondary btn-sm">
                                <i class="fa fa-fw fa-map-marker-alt"></i> Vincular domicilios
                            </button>
                        </form>
                    @endif
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-empleado-sueldos',
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => EmpleadoSueldosListadoFiltros::tieneCriteriosAplicados($filtros ?? []),
                        'limpiarUrl' => route('consultar_empleado_sueldos'),
                        'placeholder' => 'Búsqueda rápida (legajo, nombre, CUIL)…',
                        'toggleTarget' => '#panel-filtros-empleado-sueldos',
                        'toggleId' => 'btn-toggle-filtros-empleado-sueldos',
                        'inputId' => 'filtro_valor',
                        'nuevoRegistroUrl' => route('crear_empleado_sueldos', $retornoListadoQuery),
                        'nuevoRegistroCan' => 'crear-empleado-sueldos',
                    ])
                </div>
            </div>
            <form method="get" action="{{ route('consultar_empleado_sueldos') }}" id="form-filtros-empleado-sueldos" class="mb-0">
                @include('sueldos.empleado.partials.filtros_listado', [
                    'limpiarUrl' => route('consultar_empleado_sueldos'),
                ])
            </form>
            @include('sueldos.empleado.partials.filtros_externos')
            <div class="card-body table-responsive p-0">
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'lista_empleado_sueldos',
                    'queryparams' => $filtrosQuery ?? [],
                ])
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead style="background-color:#85C1E9;color:#17202A;">
                        <tr>
                            <th>Legajo</th>
                            <th>Nombre</th>
                            <th>CUIL</th>
                            <th>Empresa</th>
                            <th>Estado</th>
                            <th>Categor&iacute;a</th>
                            <th>Ingreso</th>
                            <th class="text-nowrap" style="width:70px" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($datas as $data)
                        <tr class="{{ ($data->estado ?? '') === EmpleadoEstados::PROVISORIO ? 'table-warning' : (($data->estado ?? '') === EmpleadoEstados::BAJA ? 'table-secondary' : '') }}">
                            <td>{{ $data->legajo }}</td>
                            <td>{{ $data->nombre }}</td>
                            <td>{{ $data->cuil }}</td>
                            <td>{{ optional($data->empresa)->nombre }}</td>
                            <td>{{ EmpleadoEstados::label($data->estado) }}</td>
                            <td>{{ optional($data->categoria)->descripcion }}</td>
                            <td>{{ optional($data->fecha_ingreso)->format('d/m/Y') }}</td>
                            <td class="text-nowrap align-middle">
                                @if (can('editar-empleado-sueldos', false))
                                    <a href="{{route('editar_empleado_sueldos', ['id' => $data->id] + $retornoListadoQuery)}}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                        <i class="fa fa-edit"></i>
                                    </a>
                                @endif
                                @if (can('borrar-empleado-sueldos', false))
                                    <form action="{{route('eliminar_empleado_sueldos', ['id' => $data->id])}}" class="d-inline form-eliminar" method="POST">
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
