@extends("theme.$theme.layout")
@section('titulo')
    Padrón Mipyme
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
                <h3 class="card-title">Padrón Mipyme</h3>
                <div class="card-tools">
                    <a href="{{route('crear_importacion_padron_mipyme')}}" class="btn btn-outline-secondary btn-sm">
                       	@if (can('importar-padron-mipyme', false))
                        	<i class="fa fa-fw fa-file-excel"></i> Importa Padron Mipyme
						@endif
                    </a>  
                    <a href="{{route('crear_padron_mipyme')}}" class="btn btn-outline-secondary btn-sm">
                       	@if (can('crear-padron-mipymes', false))
                        	<i class="fa fa-fw fa-plus-circle"></i> Nuevo registro
						@endif
                    </a>
                </div>
                <div class="d-md-flex justify-content-md-end">
					<form action="{{ route('padron_mipyme') }}" method="GET">
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
                @include('includes.exportar-tabla', ['ruta' => 'lista_padron_mipyme', 'busqueda' => $busqueda])
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead>
                        <tr>
                            <th class="width20">ID</th>
                            <th>Nombre</th>
                            <th>CUIT</th>
                            <th>Actividad</th>
                            <th>Fecha de Inicio</th>
                            <th class="width80" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($padron_mipymes as $data)
                        <tr>
                            <td>{{$data->id}}</td>
                            <td>{{$data->nombre}}</td>
                            <td>{{$data->cuit}}</td>
                            <td>{{$data->actividad}}</td>
                            <td>{{date("d/m/Y", strtotime($data->fechainicio ?? ''))}}</td>
                            <td>
                       			@if (can('editar-padron-mipyme', false))
                                	<a href="{{route('editar_padron_mipyme', ['id' => $data->id])}}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                    <i class="fa fa-edit"></i>
                                	</a>
								@endif
                       			@if (can('borrar-padron_mipyme', false))
                                <form action="{{route('eliminar_padron_mipyme', ['id' => $data->id])}}" class="d-inline form-eliminar" method="POST">
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
{{ $padron_mipymes->appends(['busqueda' => $busqueda])->links() }}
@endsection
