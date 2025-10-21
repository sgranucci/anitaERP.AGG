@extends("theme.$theme.layout")
@section('titulo')
Clientes UIF
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
                <h3 class="card-title">Clientes UIF</h3>
                <div class="card-tools">
                    <a href="{{route('crea_cliente_uif')}}" class="btn btn-outline-secondary btn-sm">
                       	@if (can('crear-cliente-uif', false))
                        	<i class="fa fa-fw fa-plus-circle"></i> Nuevo registro
						@endif
                    </a>
                </div>
                <div class="d-md-flex justify-content-md-end">
					<form action="{{ route('consulta_cliente_uif') }}" method="GET">
						<div class="btn-group">
							<input type="text" name="busqueda" class="form-control" placeholder="Busqueda ..."> 
							<button type="submit" class="btn btn-default">
								<span class="fa fa-search"></span>
							</button>
						</div>
					</form>
                </div>
            </div>
            <div class="card-body table-responsive p-0">
                @include('includes.exportar-tabla', ['ruta' => 'lista_cliente_uif', 'busqueda' => $busqueda])
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead>
                        <tr>
                            <th class="width10">ID</th>
                            <th>Nombre</th>
                            <th>Tipo</th>
                            <th>Número de doc.</th>
                            <th>Domicilio</th>
                            <th>Localidad</th>
                            <th>Provincia</th>
                            <th>Pais</th>
                            <th class="width10">Teléfono</th>
                            <th class="width10">Email</th>
                            <th class="width40" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($cliente_uifs as $data)
							@if ($data->estado == '1')
                        		<tr class="table-danger">
							@else
                        		<tr>
							@endif
                            <td>{{$data->id}}</td>
                            <td>{{$data->nombre}}</td>
                            <td>{{$data->abreviaturatipodocumento}}</td>
                            <td><small>{{$data->numerodocumento}}</small></td>
                            <td><small>{{$data->domicilio}}</small></td>
                            <td><small>{{$data->nombrelocalidad ?? ''}}</small></td>
                            <td><small>{{$data->nombreprovincia ?? ''}}</small></td>
                            <td><small>{{$data->nombrepais ?? ''}}</small></td>
                            <td><small>{{$data->telefono}}</small></td>
                            <td><small>{{$data->email}}</small></td>
                            <td>
                       			@if (can('editar-cliente-uif', false))
                                	<a href="{{route('edita_cliente_uif', ['id' => $data->id])}}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                    <i class="fa fa-edit"></i>
                                	</a>
								@endif
                       			@if (can('borrar-cliente-uif', false))
                                <form action="{{route('elimina_cliente_uif', ['id' => $data->id])}}" class="d-inline form-eliminar" method="POST">
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
{{ $cliente_uifs->appends(['busqueda' => $busqueda])->links() }}
@endsection
