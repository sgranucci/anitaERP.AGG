@extends("theme.$theme.layout")
@section('titulo')
    Asignación de Cajas
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
                <h3 class="card-title">Asignación de Cajas</h3>
                <div class="card-tools">
                    <a href="{{route('crea_cajaasignacion')}}" class="btn btn-outline-secondary btn-sm">
                       	@if (can('crea-asignacion-caja', false))
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
                            <th>Fecha</th>
                            <th>Empresa</th>
                            <th>Caja</th>
                            <th>Usuario</th>
                            <th class="width80" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($datas as $data)
                        <tr>
                            <td>{{$data->id}}</td>
                            <td>{{$data->fecha}}</td>
                            <td>{{$data->empresas->nombre}}</td>
                            <td>{{$data->cajas->nombre}}</td>
                            <td>{{$data->usuarios->nombre}}</td>
                            <td>
                       			@if (can('edita-asignacion-caja', false))
                                	<a href="{{route('edita_cajaasignacion', ['id' => $data->id])}}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                    <i class="fa fa-edit"></i>
                                	</a>
								@endif
                       			@if (can('borra-asignacion-caja', false))
                                <form action="{{route('elimina_cajaasignacion', ['id' => $data->id])}}" class="d-inline form-eliminar" method="POST">
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
