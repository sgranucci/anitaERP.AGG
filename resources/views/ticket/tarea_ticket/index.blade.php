@extends("theme.$theme.layout")
@section('titulo')
    Tareas
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>
@endsection

<?php use App\Helpers\biblioteca ?>

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Tareas</h3>
                <div class="card-tools">
                    <a href="{{route('crea_tarea_ticket')}}" class="btn btn-outline-secondary btn-sm">
                       	@if (can('crear-tarea-ticket', false))
                        	<i class="fa fa-fw fa-plus-circle"></i> Nuevo registro
						@endif
                    </a>
                </div>
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-striped table-bordered table-hover" id="tabla-data">
                    <thead>
                        <tr>
                            <th class="width20">ID</th>
                            <th>Nombre</th>
                            <th>Tipo de tarea</th>
                            <th>Area de destino</th>
                            <th>Tiempo estimado</th>
                            <th>Envia correo</th>
                            <th class="width80" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($datas as $data)
                        <tr>
                            <td>{{$data->id}}</td>
                            <td>{{$data->nombre}}</td>
                            <td>{{array_search($data->tipotarea, array_column($tipotarea_enum, 'valor', 'nombre'))}}</td>
                            <td>{{$data->areadestinos->nombre}}</td>
                            <td>{{$data->tiempoestimado}}</td>
                            <td>{{array_search($data->enviacorreo, array_column($enviacorreo_enum, 'valor', 'nombre'))}}</td>
                            <td>
                       			@if (can('editar-tarea-ticket', false))
                                	<a href="{{route('edita_tarea_ticket', ['id' => $data->id])}}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                    <i class="fa fa-edit"></i>
                                	</a>
								@endif
                       			@if (can('borrar-tarea-ticket', false))
                                <form action="{{route('elimina_tarea_ticket', ['id' => $data->id])}}" class="d-inline form-eliminar" method="POST">
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
@endsection
