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
                    <a href="{{route('crear_proveedor')}}" class="btn btn-outline-secondary btn-sm">
                       	@if (can('crear-proveedor', false))
                        	<i class="fa fa-fw fa-plus-circle"></i> Nuevo registro
						@endif
                    </a>
                </div>
                <div class="d-md-flex justify-content-md-end">
					<form action="{{ route('proveedor') }}" method="GET">
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
                @include('includes.exportar-tabla', ['ruta' => 'lista_proveedor', 'busqueda' => $busqueda])
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
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
                            <th>Estado</th>
                            <th class="width40" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($proveedores as $data)
							@if ($data->estado == '1')
                        		<tr class="table-danger">
							@else
                        		<tr>
							@endif
                            <td>{{$data->id}}</td>
                            <td>{{$data->nombre}}</td>
                            <td>{{$data->fantasia}}</td>
                            <td><small>{{$data->numerodocumento}}</small></td>
                            <td><small>{{$data->domicilio}}</small></td>
                            <td><small>{{$data->nombrelocalidad ?? ''}}</small></td>
                            <td><small>{{$data->nombreprovincia ?? ''}}</small></td>
                            <td><small>{{$data->codigo}}</small></td>
                            <td><small>{{$data->estado}}</small></td>
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
{{ $proveedores->appends(['busqueda' => $busqueda])->links() }}
@endsection
