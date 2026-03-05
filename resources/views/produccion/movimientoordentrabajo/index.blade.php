@extends("theme.$theme.layout")
@section('titulo')
	Movimientos de Ordenes de Trabajo
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
                <h3 class="card-title">Movimientos de OT</h3>
                <div class="card-tools">
                    <a href="{{route('crear_movimientoordentrabajo')}}" class="btn btn-outline-secondary btn-sm">
                       	@if (can('crear-movimientos-orden-trabajo', false))
                        	<i class="fa fa-fw fa-plus-circle"></i> Nuevo registro
						@endif
                    </a>
                </div>
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-striped table-bordered table-hover" id="tabla-data-2">
                    <thead>
                        <tr>
                            <th class="width20">ID</th>
                            <th>Orden trabajo</th>
                            <th>Tarea</th>
                            <th>Operacion</th>
                            <th>Empleado</th>
                            <th>Fecha</th>
                            <th>Estado</th>
                            <th class="width80" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($datas as $data)
                        <tr>
                            <td>{{$data->id}}</td>
                            <td>{{str_pad($data->codigo, 4, "0", STR_PAD_LEFT)}}</td>
                            <td>{{$data->nombretarea}}</td>
                            <td>{{$data->nombreoperacion}}</td>
                            <td>{{$data->nombreempleado}}</td>
            				<td>{{date("d/m/Y", strtotime($data->fecha ?? ''))}}</td>
                            <td>{{$estado_enum[$data->estado]}}</td>
                            <td>
                       			@if (can('editar-movimientos-orden-trabajo', false))
                                	<a href="{{route('editar_movimientoordentrabajo', ['id' => $data->id])}}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                    <i class="fa fa-edit"></i>
                                	</a>
								@endif
                       			@if (can('borrar-movimientos-orden-trabajo', false))
                                <form action="{{route('eliminar_movimientoordentrabajo', ['id' => $data->id])}}" class="d-inline form-eliminar" method="POST">
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
