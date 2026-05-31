@extends("theme.$theme.layout")
@section('titulo')
Presupuestos
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/includes/listado-filtros.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/presupuesto/presupuesto/filtro.js")}}" type="text/javascript"></script>
@endsection

<?php use App\Helpers\biblioteca;
use App\Support\Presupuesto\PresupuestoListadoFiltros; ?>

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Presupuestos</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-presupuesto',
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => PresupuestoListadoFiltros::tieneCriteriosAplicados($filtros ?? []),
                        'limpiarUrl' => route('consultar_presupuesto'),
                        'placeholder' => 'Búsqueda rápida (tolera errores de tipeo)…',
                        'toggleTarget' => '#panel-filtros-presupuesto',
                        'toggleId' => 'btn-toggle-filtros-presupuesto',
                        'inputId' => 'filtro_valor',
                        'nuevoRegistroUrl' => route('crear_presupuesto'),
                        'nuevoRegistroCan' => 'crear-presupuesto',
                    ])
                </div>
            </div>
            <form method="get" action="{{ route('consultar_presupuesto') }}" id="form-filtros-presupuesto" class="mb-0">
                @include('presupuesto.presupuesto.partials.filtros_listado', [
                    'limpiarUrl' => route('consultar_presupuesto'),
                ])
            </form>
            <div class="card-body table-responsive p-0">
                <table class="table table-striped table-bordered table-hover" id="tabla-data-2">
                    <thead>
                        <tr>
                            <th class="width20">ID</th>
                            <th>Nombre</th>
                            <th>Código</th>
                            <th>Año</th>
                            <th>Detalle</th>
                            <th>Estado</th>
                            <th>Escenarios</th>
                            <th class="width80" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($datas as $data)
                        <tr>
                            <td>{{$data->id}}</td>
                            <td>{{$data->nombre}}</td>
                            <td>{{$data->codigo}}</td>
                            <td>{{$data->anio}}</td>
                            <td>{{$data->detalle??''}}</td>
                            <td>{{$data->estado}}</td>
                            <td>                                
                                <ul>
                                    @foreach($data->presupuesto_escenarios as $escenario)
                                        <li>{{$escenario->nombre}}-{{$escenario->tipo}}</li>
                                    @endforeach
                                </ul>
                            </td>                            
                            <td>
                       			@if (can('editar-presupuesto', false))
                                	<a href="{{route('editar_presupuesto', ['id' => $data->id])}}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                    <i class="fa fa-edit"></i>
                                	</a>
								@endif
                       			@if (can('borrar-presupuesto', false))
                                <form action="{{route('eliminar_presupuesto', ['id' => $data->id])}}" class="d-inline form-eliminar" method="POST">
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
