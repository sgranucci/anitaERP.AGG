@extends("theme.$theme.layout")
@section('titulo')
Proveedores
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
                <h3 class="card-title">Proveedores</h3>
                <div class="card-tools">
                    <a href="{{route('crear_cliente')}}" class="btn btn-outline-secondary btn-sm">
                       	@if (can('crear-proveedor', false))
                        	<i class="fa fa-fw fa-plus-circle"></i> Nuevo registro
						@endif
                    </a>
                </div>
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-striped table-bordered table-hover" id="tabla-data">
                    <thead>
                        <tr>
                            <th class="width10">ID</th>
                            <th>Nombre</th>
                            <th>Nombre de Fantas&iacute;a</th>
                            <th>C.U.I.T.</th>
                            <th>Domicilio</th>
                            <th>Localidad</th>
                            <th>Provincia</th>
                            <th class="width10">C&oacute;d.</th>
                            <th class="width40" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($datas as $data)
							@if ($data->estado == '1')
                        		<tr class="table-danger">
							@else
                        		<tr>
							@endif
                            <td>{{$data->id}}</td>
                            <td>{{$data->nombre}}</td>
                            <td>{{$data->fantasia}}</td>
                            <td><small>{{$data->nroinscripcion}}</small></td>
                            <td><small>{{$data->domicilio}}</small></td>
                            <td><small>{{($data->localidades ?? '' ? $data->localidades->nombre : '')}}</small></td>
                            <td><small>{{($data->provincias ?? '' ? $data->provincias->nombre : '')}}</small></td>
                            <td><small>{{$data->codigo}}</small></td>
                            <td>
                       			@if (can('editar-proveedor', false))
                                	<a href="{{route('editar_proveedor', ['id' => $data->id])}}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                    <i class="fa fa-edit"></i>
                                	</a>
								@endif
                       			@if (can('borrar-proveedor', false))
                                <form action="{{route('eliminar_proveedor', ['id' => $data->id])}}" class="d-inline form-eliminar" method="POST">
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
